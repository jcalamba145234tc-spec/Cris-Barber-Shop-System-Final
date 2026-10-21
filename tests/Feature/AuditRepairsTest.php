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
use App\Services\QuantityAudit;
use App\Services\TransactionWorkflow;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuditRepairsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $barberUser;

    private Barber $barber;

    private Product $product;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->barberUser = User::factory()->create(['role' => 'barber']);
        $this->barber = Barber::create(['user_id' => $this->barberUser->id, 'first_name' => 'Audit', 'last_name' => 'Barber', 'hired_date' => today(), 'status' => 'active', 'availability' => 'available']);
        $this->product = Product::create(['name' => 'Wax', 'stock' => 10, 'low_stock_threshold' => 5, 'unit' => 'piece', 'cost' => '25.50', 'active' => true]);
        $this->service = Service::create(['name' => 'Haircut', 'price' => '175.51', 'duration_minutes' => 30, 'active' => true]);
        $this->service->products()->attach($this->product->id, ['quantity' => 2]);
    }

    private function checkout(array $overrides = []): array
    {
        return array_replace(['checkout_token' => (string) Str::uuid(), 'barber_id' => $this->barber->id,
            'service_id' => $this->service->id, 'quantity' => 1, 'payment_method' => 'cash',
            'cash_received' => '200.00', 'status' => 'completed'], $overrides);
    }

    private function productData(array $overrides = []): array
    {
        return array_replace(['name' => 'New product', 'unit' => 'piece', 'stock' => 0,
            'low_stock_threshold' => 5, 'cost' => '25.50', 'active' => 1], $overrides);
    }

    private function legacyProducts(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Synthetic pre-conversion fixtures use SQLite affinity and triggers; HTTP validation runs on both engines.');
        }
        // Only in-memory synthetic fixtures: emulate a database before guarded conversion.
        DB::unprepared('DROP TRIGGER whole_products_insert');
        DB::unprepared('DROP TRIGGER whole_products_update');
    }

    public function test_opening_stock_rejects_fractional_negative_and_oversized_quantities(): void
    {
        foreach (['1.5', '2.50', '0.75', '3.25', -1, 1000000000] as $quantity) {
            $this->actingAs($this->admin)->post('/inventory', $this->productData(['stock' => $quantity]))->assertSessionHasErrors('stock');
        }
        $this->assertSame(1, Product::count());
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_zero_and_positive_whole_opening_quantities_preserve_decimal_costs(): void
    {
        foreach ([0, 1, 2, 10, 100] as $quantity) {
            $this->actingAs($this->admin)->post('/inventory', $this->productData(['name' => 'Whole '.$quantity, 'stock' => $quantity]))->assertSessionHasNoErrors();
            $product = Product::where('name', 'Whole '.$quantity)->firstOrFail();
            $this->assertEquals($quantity, $product->stock);
            $this->assertSame('25.50', $product->cost);
            $this->assertDatabaseHas('inventory_movements', ['product_id' => $product->id, 'quantity' => $quantity, 'balance' => $quantity]);
        }
    }

    public function test_edit_rejects_fractional_threshold_and_direct_stock_overwrite(): void
    {
        $data = $this->productData(['low_stock_threshold' => '2.50']);
        unset($data['stock']);
        $this->actingAs($this->admin)->put('/inventory/'.$this->product->id, $data)->assertSessionHasErrors('low_stock_threshold');
        $data['low_stock_threshold'] = 5;
        $data['stock'] = '1.5';
        $this->put('/inventory/'.$this->product->id, $data)->assertSessionHasErrors('stock');
        $this->assertEquals(10, $this->product->fresh()->stock);
    }

    public function test_restock_rejects_decimal_negative_zero_and_missing_token(): void
    {
        foreach (['1.5', '2.50', '0.75', -1, 0] as $quantity) {
            $this->actingAs($this->admin)->post('/inventory/'.$this->product->id.'/restock', ['quantity' => $quantity, 'restock_token' => (string) Str::uuid()])->assertSessionHasErrors('quantity');
        }
        $this->post('/inventory/'.$this->product->id.'/restock', ['quantity' => 1])->assertSessionHasErrors('restock_token');
        $this->assertEquals(10, $this->product->fresh()->stock);
    }

    public function test_identical_restock_is_idempotent_and_token_mismatch_is_rejected(): void
    {
        $data = ['quantity' => 2, 'restock_token' => (string) Str::uuid()];
        $this->actingAs($this->admin)->post('/inventory/'.$this->product->id.'/restock', $data)->assertSessionHasNoErrors();
        $this->post('/inventory/'.$this->product->id.'/restock', $data)->assertSessionHasNoErrors();
        $this->post('/inventory/'.$this->product->id.'/restock', array_replace($data, ['quantity' => 3]))->assertSessionHasErrors('restock_token');
        $this->assertEquals(12, $this->product->fresh()->stock);
        $this->assertSame(1, InventoryMovement::where('type', 'restock')->count());
    }

    public function test_pos_rejects_decimal_negative_and_zero_quantity_without_writes(): void
    {
        foreach (['1.5', '2.50', '0.75', -1, 0] as $quantity) {
            $this->actingAs($this->admin)->post('/transactions', $this->checkout(['quantity' => $quantity]))->assertSessionHasErrors('quantity');
        }
        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, Payment::count());
        $this->assertEquals(10, $this->product->fresh()->stock);
    }

    public function test_workflow_cannot_bypass_integer_validation(): void
    {
        try {
            app(TransactionWorkflow::class)->create($this->checkout(['quantity' => '1.5']), $this->admin);
            $this->fail('Fractional quantity must not be cast silently.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
        $this->assertSame(0, Transaction::count());
    }

    public function test_odd_cent_monetary_values_preserve_existing_half_up_split(): void
    {
        $this->actingAs($this->admin)->post('/transactions', $this->checkout())->assertSessionHasNoErrors();
        $sale = Transaction::firstOrFail();
        $this->assertEquals('175.51', $sale->total_amount);
        $this->assertEquals('87.76', $sale->barber_share);
        $this->assertEquals('87.75', $sale->shop_share);
        $this->assertSame('175.51', $sale->payment->amount);
        $this->assertSame('24.49', $sale->payment->change_amount);
        $this->assertSame('25.50', $this->product->fresh()->cost);
        $this->assertEquals(8, $this->product->fresh()->stock);
    }

    public function test_gcash_replay_returns_one_pending_receipt_and_rejects_changed_payload(): void
    {
        $data = $this->checkout(['payment_method' => 'gcash', 'gcash_reference_no' => '1234567890123']);
        $this->actingAs($this->admin)->post('/transactions', $data)->assertSessionHasNoErrors();
        $this->post('/transactions', $data)->assertSessionHasNoErrors();
        $this->post('/transactions', array_replace($data, ['quantity' => 2]))->assertSessionHasErrors('checkout_token');
        $this->assertSame(1, Transaction::count());
        $this->assertSame(1, Payment::count());
        $this->assertSame('pending', Payment::firstOrFail()->verification_status);
        $this->assertEquals(10, $this->product->fresh()->stock);
    }

    public function test_verification_rechecks_stock_without_deduction_then_completion_rechecks_again(): void
    {
        $sale = app(TransactionWorkflow::class)->create($this->checkout(['payment_method' => 'gcash', 'gcash_reference_no' => '123456789']), $this->admin);
        $this->product->update(['stock' => 1]);
        $url = '/payments/'.$sale->payment->id.'/verify';
        $this->actingAs($this->admin)->post($url, ['status' => 'verified'])->assertSessionHasErrors('stock');
        $this->assertSame('pending', $sale->payment()->first()->verification_status);
        $this->product->update(['stock' => 2]);
        $this->post($url, ['status' => 'verified'])->assertSessionHasNoErrors();
        $this->post($url, ['status' => 'verified'])->assertSessionHasNoErrors();
        $this->assertEquals(2, $this->product->fresh()->stock);
        $this->assertSame(0, InventoryMovement::count());
        $this->product->update(['stock' => 0]);
        $this->patch('/transactions/'.$sale->id.'/status', ['status' => 'completed'])->assertSessionHasErrors('stock');
        $this->assertSame('queued', $sale->fresh()->status);
        $this->assertNull($sale->fresh()->inventory_deducted_at);
    }

    public function test_completed_payment_cannot_be_flagged_and_sales_remain_eligible(): void
    {
        $sale = app(TransactionWorkflow::class)->create($this->checkout(['payment_method' => 'gcash', 'gcash_reference_no' => '123456789']), $this->admin);
        $url = '/payments/'.$sale->payment->id.'/verify';
        $this->actingAs($this->admin)->post($url, ['status' => 'verified'])->assertSessionHasNoErrors();
        $this->patch('/transactions/'.$sale->id.'/status', ['status' => 'completed'])->assertSessionHasNoErrors();
        $this->post($url, ['status' => 'flagged'])->assertSessionHasErrors('payment');
        $this->post($url, ['status' => 'verified'])->assertSessionHasNoErrors();
        $this->get('/dashboard')->assertViewHas('totalRevenue', 175.51);
        $this->get('/reports/pdf')->assertViewHas('grossSales', 175.51)->assertViewHas('shopShare', 87.75);
        $this->get('/transactions/'.$sale->id)->assertDontSee('Flag payment')->assertDontSee('Remove record');
    }

    public function test_inactive_barber_cannot_login_or_use_existing_session_but_can_logout(): void
    {
        $this->barberUser->update(['password' => 'Valid-password-123']);
        $this->barber->update(['status' => 'inactive']);
        $this->post('/login', ['email' => $this->barberUser->email, 'password' => 'Valid-password-123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        foreach (['/dashboard', '/profile', '/transactions', '/attendance'] as $url) {
            $this->actingAs($this->barberUser)->get($url)->assertForbidden();
        }
        $this->post('/attendance/clock', ['action' => 'in'])->assertForbidden();
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_deactivation_blocks_existing_queue_status_write(): void
    {
        $sale = app(TransactionWorkflow::class)->create($this->checkout(['status' => 'queued']), $this->admin);
        $this->barber->update(['status' => 'inactive']);
        $this->actingAs($this->barberUser)->patch('/transactions/'.$sale->id.'/status', ['status' => 'in_progress'])->assertForbidden();
        $this->assertSame('queued', $sale->fresh()->status);
    }

    public function test_dashboard_counts_all_catalogue_barbers_and_nondeleted_payment_rows(): void
    {
        Customer::create(['name' => 'Customer']);
        $this->service->update(['active' => false]);
        $this->actingAs($this->admin)->get('/dashboard')->assertViewHas('totalCustomers', 1)
            ->assertViewHas('totalBarbers', 1)->assertViewHas('availableBarbers', 1)
            ->assertViewHas('totalServices', 1)->assertViewHas('totalPayments', 0)->assertViewHas('totalRevenue', 0);
    }

    public function test_archived_customer_stays_linked_but_cannot_be_selected_for_new_sale(): void
    {
        $customer = Customer::create(['name' => 'Historical customer']);
        $sale = app(TransactionWorkflow::class)->create($this->checkout(['customer_id' => $customer->id]), $this->admin);
        $this->actingAs($this->admin)->delete('/customers/'.$customer->id)->assertRedirect('/customers');
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertSame($customer->id, $sale->fresh()->customer_id);
        $this->assertSame('Historical customer', $sale->fresh()->customer->name);
        $this->post('/transactions', $this->checkout(['customer_id' => $customer->id]))->assertSessionHasErrors('customer_id');
        $this->get('/transactions/'.$sale->id)->assertSee('Historical customer');
        $this->get('/transactions/'.$sale->id.'/edit')->assertSee('Historical customer');
    }

    public function test_completed_date_and_removal_protection_do_not_change_history_or_totals(): void
    {
        $this->travelTo(now()->startOfMonth()->addDays(2)->setTime(12, 0, 37));
        $sale = app(TransactionWorkflow::class)->create($this->checkout(), $this->admin);
        $original = $sale->transaction_date->format('Y-m-d H:i:s');
        $this->actingAs($this->admin)->put('/transactions/'.$sale->id, ['transaction_date' => now()->subMonth()->format('Y-m-d\TH:i')])->assertSessionHasErrors('transaction_date');
        $this->delete('/transactions/'.$sale->id)->assertSessionHasErrors('transaction');
        $this->assertSame($original, $sale->fresh()->transaction_date->format('Y-m-d H:i:s'));
        $this->get('/reports/pdf?period=date&date='.today()->format('Y-m-d'))->assertViewHas('grossSales', 175.51);
        $this->get('/reports/pdf?period=month&month='.now()->subMonth()->format('Y-m'))->assertViewHas('grossSales', 0);
    }

    public function test_queued_date_correction_and_soft_removal_remain_available(): void
    {
        $sale = app(TransactionWorkflow::class)->create($this->checkout(['status' => 'queued']), $this->admin);
        $date = now()->subDay()->format('Y-m-d\TH:i');
        $this->actingAs($this->admin)->put('/transactions/'.$sale->id, ['transaction_date' => $date])->assertSessionHasNoErrors();
        $this->assertSame($date, $sale->fresh()->transaction_date->format('Y-m-d\TH:i'));
        $this->delete('/transactions/'.$sale->id)->assertRedirect('/transactions');
        $this->assertSoftDeleted('transactions', ['id' => $sale->id]);
        $this->assertEquals(10, $this->product->fresh()->stock);
        $this->assertDatabaseHas('payments', ['transaction_id' => $sale->id]);
    }

    public function test_guarded_conversion_aborts_without_rounding_legacy_values(): void
    {
        $this->legacyProducts();
        $this->product->update(['stock' => '1.235', 'low_stock_threshold' => '1.234']);
        $issues = iterator_to_array(app(QuantityAudit::class)->issues());
        $this->assertCount(2, $issues);
        $migration = require database_path('migrations/2026_10_15_000002_guard_whole_inventory_quantities.php');
        try {
            $migration->up();
            $this->fail('Must not convert fractional data.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Integer conversion blocked', $exception->getMessage());
        }
        $this->assertSame('1.235', $this->product->fresh()->stock);
        $this->assertSame('1.234', $this->product->fresh()->low_stock_threshold);
        $this->assertFalse(Service::available()->whereKey($this->service->id)->exists());
        $this->actingAs($this->admin)->get('/inventory')->assertSee('1.235 (legacy — review required)');
        $this->post('/inventory/'.$this->product->id.'/restock', ['quantity' => 1, 'restock_token' => (string) Str::uuid()])->assertSessionHasErrors('stock');
        $this->assertSame('1.235', $this->product->fresh()->stock);
    }

    public function test_preflight_is_read_only_and_detects_synthetic_fractional_records(): void
    {
        $this->artisan('shop:audit-data')->assertSuccessful();
        $this->legacyProducts();
        $this->product->update(['stock' => '2.5']);
        $this->artisan('shop:audit-data')->assertFailed();
        $this->assertSame('2.500', $this->product->fresh()->stock);
    }

    public function test_sqlite_rejects_fractional_inventory_even_through_direct_database_write(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite trigger-specific regression; HTTP whole-number validation is tested on both engines.');
        }
        $this->expectException(QueryException::class);
        DB::table('products')->where('id', $this->product->id)->update(['stock' => '1.5']);
    }

    public function test_payment_unique_constraint_prevents_second_receipt_for_transaction(): void
    {
        $sale = app(TransactionWorkflow::class)->create($this->checkout(), $this->admin);
        $this->expectException(QueryException::class);
        Payment::create(['transaction_id' => $sale->id, 'payment_method' => 'cash', 'amount' => '175.51', 'verification_status' => 'verified']);
    }

    public function test_quantity_inputs_and_movement_display_are_integral_money_remains_decimal(): void
    {
        $this->actingAs($this->admin)->get('/inventory/create')->assertSee('step="1"', false)->assertSee('step="0.01"', false)->assertDontSee('step="0.001"', false);
        $this->get('/services/'.$this->service->id.'/edit')->assertSee('step="1"', false)->assertDontSee('step="0.001"', false);
        $this->post('/inventory/'.$this->product->id.'/restock', ['quantity' => 2, 'restock_token' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->get('/inventory/'.$this->product->id)->assertSee('12 piece')->assertSee('₱25.50')->assertSee('<td>2</td><td>12</td>', false)->assertDontSee('12.00 piece');
    }

    public function test_report_filters_reject_invalid_dates_and_empty_reports_stream(): void
    {
        $this->actingAs($this->admin)->get('/reports/pdf?date=2026-99-99')->assertSessionHasErrors('date');
        $this->get('/reports/csv?period=bad')->assertSessionHasErrors('period');
        $response = $this->get('/reports/csv');
        $response->assertOk();
        $this->assertStringContainsString('Cash,0,0.00', $response->streamedContent());
    }

    public function test_admin_can_edit_barber_identity_without_replacing_history(): void
    {
        $sale = app(TransactionWorkflow::class)->create($this->checkout(), $this->admin);
        $data = ['name' => 'Updated Barber', 'email' => 'updated@crisbarber.com', 'phone_number' => '09123456789', 'hired_date' => today()->format('Y-m-d')];
        $this->actingAs($this->admin)->get('/barbers/'.$this->barber->id.'/edit')->assertRedirect(route('barbers.index', ['edit' => $this->barber->id]));
        $this->put('/barbers/'.$this->barber->id, $data)->assertRedirect('/barbers')->assertSessionHasNoErrors();
        $this->assertSame('Updated Barber', $this->barber->fresh()->name);
        $this->assertSame($this->barberUser->id, $this->barber->fresh()->user_id);
        $this->assertSame($this->barber->id, $sale->fresh()->barber_id);
        $this->assertSame('barber', $this->barberUser->fresh()->role);
        $this->assertSame('updated@crisbarber.com', $this->barberUser->fresh()->email);
    }

    public function test_barber_account_edit_cannot_be_used_for_role_escalation_or_by_barbers(): void
    {
        $data = ['name' => 'Updated', 'email' => 'updated@crisbarber.com', 'hired_date' => today()->format('Y-m-d'), 'role' => 'admin'];
        $this->actingAs($this->admin)->put('/barbers/'.$this->barber->id, $data)->assertSessionHasErrors('role');
        $this->actingAs($this->barberUser)->get('/barbers/'.$this->barber->id.'/edit')->assertForbidden();
        $this->put('/barbers/'.$this->barber->id, $data)->assertForbidden();
        $this->assertSame('barber', $this->barberUser->fresh()->role);
    }

    public function test_malformed_checkout_token_is_validation_error_not_database_error(): void
    {
        $this->actingAs($this->admin)->post('/transactions', $this->checkout(['checkout_token' => ['unexpected']]))->assertSessionHasErrors('checkout_token');
        $this->assertSame(0, Transaction::count());
        $this->assertEquals(10, $this->product->fresh()->stock);
    }

    public function test_database_failure_during_payment_insert_rolls_back_checkout_and_snapshots(): void
    {
        Payment::creating(function (Payment $payment): void {
            // Synthetic FK violation after transaction and inventory snapshots were inserted.
            $payment->transaction_id = (string) Str::uuid();
        });
        try {
            app(TransactionWorkflow::class)->create($this->checkout(), $this->admin);
            $this->fail('The synthetic database failure must be raised.');
        } catch (QueryException $exception) {
            $this->assertSame('23000', (string) $exception->errorInfo[0]);
        } finally {
            Payment::flushEventListeners();
        }
        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, DB::table('transaction_products')->count());
        $this->assertSame(0, InventoryMovement::count());
        $this->assertEquals(10, $this->product->fresh()->stock);
    }
}
