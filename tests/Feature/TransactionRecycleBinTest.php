<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionRecycleBinTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $barberUser;

    private Barber $barber;

    private Service $service;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->barberUser = User::factory()->create(['role' => 'barber']);
        $this->barber = Barber::create(['user_id' => $this->barberUser->id, 'first_name' => 'Archive', 'last_name' => 'Barber', 'hired_date' => today(), 'status' => 'active', 'availability' => 'available']);
        $this->product = Product::create(['name' => 'Archive Wax', 'stock' => 10, 'unit' => 'portion', 'low_stock_threshold' => 2, 'active' => true]);
        $this->service = Service::create(['name' => 'Archive Haircut', 'price' => '150.00', 'duration_minutes' => 30, 'active' => true]);
        $this->service->products()->attach($this->product->id, ['quantity' => 2]);
    }

    private function queued(array $overrides = []): Transaction
    {
        return app(TransactionWorkflow::class)->create(array_replace([
            'checkout_token' => (string) Str::uuid(), 'barber_id' => $this->barber->id,
            'service_id' => $this->service->id, 'quantity' => 1, 'payment_method' => 'cash',
            'cash_received' => '200.00', 'status' => 'queued',
        ], $overrides), $this->admin);
    }

    public function test_archiving_moves_the_record_to_the_admin_recycle_bin(): void
    {
        $record = $this->queued();
        $this->actingAs($this->admin)->delete(route('transactions.destroy', $record))->assertRedirect(route('transactions.index'));
        $this->assertSoftDeleted('transactions', ['id' => $record->id]);
        $this->get(route('transactions.index'))->assertDontSee($record->id)->assertSee('Recycle Bin');
        $this->get(route('transactions.recycle-bin'))->assertOk()->assertSee($record->id)->assertSee('Archive Haircut')->assertSee('Restore');
        $this->get(route('transactions.show', $record->id))->assertNotFound();
    }

    public function test_archived_detail_is_read_only_and_regular_write_routes_remain_blocked(): void
    {
        $record = $this->queued(['payment_method' => 'gcash', 'gcash_reference_no' => '1234567890123']);
        $record->delete();
        $this->actingAs($this->admin)->get(route('transactions.archived-show', $record->id))->assertOk()
            ->assertSee('This record is archived')->assertSee('Restore transaction')->assertSee('₱150.00')
            ->assertDontSee('Start service')->assertDontSee('Complete &amp; deduct stock', false)
            ->assertDontSee('Verify GCash')->assertDontSee('Flag payment')->assertDontSee('Archive record')
            ->assertDontSee('Edit customer / date');
        $this->patch(route('transactions.status', $record->id), ['status' => 'completed'])->assertNotFound();
        $this->put(route('transactions.update', $record->id), [])->assertNotFound();
        $this->delete(route('transactions.destroy', $record->id))->assertNotFound();
        $this->post(route('payments.verify', $record->payment), ['status' => 'verified'])->assertNotFound();
    }

    public function test_restore_preserves_status_finances_stock_snapshots_and_checkout_identity(): void
    {
        foreach (['queued', 'in_progress', 'ready', 'cancelled'] as $status) {
            $record = $this->queued();
            $record->update(['status' => $status]);
            $record->delete();
            $before = $record->fresh()->getRawOriginal();
            $payment = $record->payment->getRawOriginal();
            $services = DB::table('transaction_services')->where('transaction_id', $record->id)->get()->toJson();
            $products = DB::table('transaction_products')->where('transaction_id', $record->id)->get()->toJson();
            $movements = InventoryMovement::count();
            $this->actingAs($this->admin)->post(route('transactions.restore', $record->id), ['status' => 'completed', 'total_amount' => 1])
                ->assertRedirect(route('transactions.show', $record->id))->assertSessionHas('status');
            $restored = $record->fresh();
            $this->assertFalse($restored->trashed());
            foreach ($before as $key => $value) {
                if (! in_array($key, ['deleted_at', 'updated_at'])) {
                    $this->assertSame($value, $restored->getRawOriginal($key), $key.' remains unchanged');
                }
            }
            $this->assertSame($payment, $restored->payment->getRawOriginal());
            $this->assertSame($services, DB::table('transaction_services')->where('transaction_id', $record->id)->get()->toJson());
            $this->assertSame($products, DB::table('transaction_products')->where('transaction_id', $record->id)->get()->toJson());
            $this->assertSame($movements, InventoryMovement::count());
            $this->assertEquals(10, $this->product->fresh()->stock);
            $this->get(route('transactions.index'))->assertSee($record->id);
            $this->get(route('transactions.recycle-bin'))->assertDontSee($record->id);
            // Remove each synthetic fixture from the active queue before the next checkout.
            $restored->delete();
        }
    }

    public function test_barbers_and_guests_cannot_list_view_or_restore_archived_transactions(): void
    {
        $record = $this->queued();
        $record->delete();
        foreach ([route('transactions.recycle-bin'), route('transactions.archived-show', $record->id)] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        $this->post(route('transactions.restore', $record->id))->assertRedirect(route('login'));
        $this->actingAs($this->barberUser);
        $this->get(route('transactions.recycle-bin'))->assertForbidden();
        $this->get(route('transactions.archived-show', $record->id))->assertForbidden();
        $this->post(route('transactions.restore', $record->id))->assertForbidden();
        $this->get(route('transactions.index'))->assertDontSee('Recycle Bin');
        $this->assertTrue($record->fresh()->trashed());
    }

    public function test_search_only_returns_archived_matches_and_keeps_archived_customers_linked(): void
    {
        $customer = Customer::create(['name' => 'Archive Customer Zero 0']);
        $record = $this->queued(['customer_id' => $customer->id, 'payment_method' => 'gcash', 'gcash_reference_no' => '1234567890123']);
        $record->delete();
        $customer->delete();
        $active = $this->queued();
        $this->actingAs($this->admin);
        foreach (['0', 'Archive Customer', 'Archive Haircut', '1234567890123', $record->id] as $term) {
            $this->get(route('transactions.recycle-bin', ['q' => $term]))->assertOk()
                ->assertViewHas('transactions', fn ($rows) => $rows->total() === 1)->assertSee($record->id)->assertDontSee($active->id);
        }
        $this->get(route('transactions.recycle-bin', ['q' => 'no-match']))->assertOk()->assertSee('No archived transactions match your search.');
        $this->get(route('transactions.archived-show', $record->id))->assertSee('Archive Customer Zero 0 (archived)');
        $this->post(route('transactions.restore', $record->id))->assertRedirect();
        $this->assertTrue($customer->fresh()->trashed());
        $this->assertSame($customer->id, $record->fresh()->customer_id);
    }

    public function test_recycle_bin_search_validation_and_markup_are_safe(): void
    {
        $record = $this->queued();
        $record->update(['service_name' => '<script>alert(1)</script>']);
        $record->services()->detach();
        $record->delete();
        $this->actingAs($this->admin)->get(route('transactions.recycle-bin'))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('transactions.recycle-bin', ['q' => str_repeat('x', 101)]))->assertSessionHasErrors('q');
        $this->get(route('transactions.recycle-bin', ['q' => ['invalid']]))->assertSessionHasErrors('q');
    }

    public function test_pagination_is_bounded_and_preserves_search(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $this->queued()->delete();
        }
        $this->actingAs($this->admin)->get(route('transactions.recycle-bin', ['q' => 'Archive Haircut']))->assertOk()
            ->assertViewHas('transactions', fn ($rows) => $rows->total() === 21 && $rows->count() === 20)
            ->assertSee('page=2', false)->assertSee('q=Archive%20Haircut', false);
    }

    public function test_active_missing_and_already_restored_records_cannot_be_restored_again(): void
    {
        $record = $this->queued();
        $this->actingAs($this->admin)->get(route('transactions.archived-show', $record->id))->assertNotFound();
        $this->post(route('transactions.restore', $record->id))->assertNotFound();
        $this->post(route('transactions.restore', (string) Str::uuid()))->assertNotFound();
        $record->delete();
        $this->post(route('transactions.restore', $record->id))->assertRedirect();
        $this->post(route('transactions.restore', $record->id))->assertNotFound();
        $this->assertSame(1, Transaction::count());
        $this->assertSame(1, Payment::count());
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_restoring_an_existing_consumed_snapshot_does_not_consume_or_return_stock(): void
    {
        // Simulate a historical archived record; the current archive endpoint still blocks completed sales.
        $record = $this->queued(['status' => 'completed']);
        $before = InventoryMovement::where('transaction_id', $record->id)->get()->toJson();
        $payment = $record->payment->getRawOriginal();
        $record->delete();
        $this->actingAs($this->admin)->post(route('transactions.restore', $record->id))->assertRedirect();
        $this->assertEquals(8, $this->product->fresh()->stock);
        $this->assertSame($before, InventoryMovement::where('transaction_id', $record->id)->get()->toJson());
        $this->assertSame($payment, $record->fresh()->payment->getRawOriginal());
        $this->assertSame('completed', $record->fresh()->status);
        $this->assertNotNull($record->fresh()->inventory_deducted_at);
        $this->assertSame($record->id, $this->queued(['checkout_token' => $record->checkout_token, 'status' => 'completed'])->id);
        $this->assertSame(1, Transaction::count());
        $this->assertSame(1, Payment::count());
        $this->assertEquals(8, $this->product->fresh()->stock);
    }

    public function test_restore_does_not_verify_a_pending_gcash_payment(): void
    {
        $record = $this->queued(['payment_method' => 'gcash', 'gcash_reference_no' => '9876543210987']);
        $record->delete();
        $this->actingAs($this->admin)->post(route('transactions.restore', $record->id))->assertRedirect();
        $this->assertSame('pending', $record->fresh()->payment->verification_status);
        $this->assertSame('queued', $record->fresh()->status);
        $this->assertNull($record->fresh()->inventory_deducted_at);
        $this->assertEquals(10, $this->product->fresh()->stock);
        $this->get(route('transactions.show', $record))->assertSee('Verify GCash');
    }

    public function test_completed_sales_remain_protected_and_do_not_enter_the_bin(): void
    {
        $record = $this->queued(['status' => 'completed']);
        $this->actingAs($this->admin)->delete(route('transactions.destroy', $record))->assertSessionHasErrors('transaction');
        $this->assertFalse($record->fresh()->trashed());
        $this->get(route('transactions.recycle-bin'))->assertOk()->assertSee('The recycle bin is empty.')->assertDontSee($record->id);
        $this->get(route('transactions.show', $record))->assertOk()->assertDontSee('Archive record');
    }
}
