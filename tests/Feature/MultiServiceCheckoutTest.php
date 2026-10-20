<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Edge cases for selecting several services (with quantities) in one sale. */
class MultiServiceCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Barber $barber;

    private Service $haircut;

    private Service $beardTrim;

    private Product $wax;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $barberUser = User::factory()->create(['role' => 'barber']);
        $this->barber = Barber::create(['user_id' => $barberUser->id, 'first_name' => 'Jane', 'last_name' => 'Barber', 'hired_date' => today(), 'status' => 'active', 'availability' => 'available']);
        $this->wax = Product::create(['name' => 'Wax', 'stock' => 10, 'unit' => 'portion', 'low_stock_threshold' => 5, 'active' => true]);
        $this->haircut = Service::create(['name' => 'Haircut', 'price' => 150, 'duration_minutes' => 30, 'active' => true]);
        $this->haircut->products()->attach($this->wax->id, ['quantity' => 2]);
        $this->beardTrim = Service::create(['name' => 'Beard Trim', 'price' => 70, 'duration_minutes' => 15, 'active' => true]);
        $this->beardTrim->products()->attach($this->wax->id, ['quantity' => 1]);
    }

    private function cart(array $quantities, array $overrides = []): array
    {
        return array_replace([
            'checkout_token' => (string) Str::uuid(),
            'barber_id' => $this->barber->id,
            'service_ids' => array_keys($quantities),
            'service_quantities' => $quantities,
            'payment_method' => 'cash', 'cash_received' => '5000.00', 'status' => 'completed',
        ], $overrides);
    }

    public function test_combined_supply_need_cannot_exceed_stock_even_when_each_service_fits_alone(): void
    {
        // Haircut x3 needs 6 and Beard Trim x5 needs 5. Each fits in a stock of 10, together they need 11.
        $this->actingAs($this->admin)
            ->post('/transactions', $this->cart([$this->haircut->id => 3, $this->beardTrim->id => 5]))
            ->assertSessionHasErrors('service_ids');

        $this->assertSame(0, Transaction::count());
        $this->assertEquals(10, $this->wax->fresh()->stock);
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_an_inactive_service_in_the_cart_rejects_the_whole_sale_without_partial_changes(): void
    {
        $this->beardTrim->update(['active' => false]);

        $this->actingAs($this->admin)
            ->post('/transactions', $this->cart([$this->haircut->id => 1, $this->beardTrim->id => 1]))
            ->assertSessionHasErrors();

        $this->assertSame(0, Transaction::count());
        $this->assertEquals(10, $this->wax->fresh()->stock);
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_duplicate_services_and_non_whole_or_zero_quantities_are_rejected(): void
    {
        $this->actingAs($this->admin);

        $this->post('/transactions', $this->cart([$this->haircut->id => 1], ['service_ids' => [$this->haircut->id, $this->haircut->id]]))->assertSessionHasErrors();
        $this->post('/transactions', $this->cart([$this->haircut->id => '1.5']))->assertSessionHasErrors();
        $this->post('/transactions', $this->cart([$this->haircut->id => 0]))->assertSessionHasErrors();

        $this->assertSame(0, Transaction::count());
        $this->assertEquals(10, $this->wax->fresh()->stock);
    }

    public function test_a_later_price_change_does_not_alter_a_recorded_sale(): void
    {
        $this->actingAs($this->admin)
            ->post('/transactions', $this->cart([$this->haircut->id => 1, $this->beardTrim->id => 1]))
            ->assertSessionHasNoErrors();
        $transaction = Transaction::firstOrFail();

        Service::whereKey($this->haircut->id)->update(['price' => 999]);

        $this->assertEquals(220, $transaction->fresh()->total_amount);
        $this->assertEquals(150, $transaction->services()->where('services.id', $this->haircut->id)->first()->pivot->unit_price);
        $this->get('/transactions/'.$transaction->id)->assertOk()->assertSee('150.00')->assertDontSee('999.00');
    }

    public function test_gcash_sale_with_several_services_deducts_the_combined_supplies_once_after_verification(): void
    {
        $this->actingAs($this->admin)
            ->post('/transactions', $this->cart([$this->haircut->id => 2, $this->beardTrim->id => 1], ['payment_method' => 'gcash', 'gcash_reference_no' => '9876543210123', 'cash_received' => null]))
            ->assertSessionHasNoErrors();
        $transaction = Transaction::firstOrFail();

        $this->assertSame('queued', $transaction->status);
        $this->assertEquals(10, $this->wax->fresh()->stock);

        $this->post('/payments/'.$transaction->payment->id.'/verify', ['status' => 'verified'])->assertSessionHasNoErrors();
        $this->patch('/transactions/'.$transaction->id.'/status', ['status' => 'completed'])->assertSessionHasNoErrors();
        $this->patch('/transactions/'.$transaction->id.'/status', ['status' => 'completed']);

        $this->assertEquals(5, $this->wax->fresh()->stock); // (2 x 2) + (1 x 1) = 5, deducted exactly once
        $this->assertSame(1, InventoryMovement::where('transaction_id', $transaction->id)->count());
    }

    public function test_sales_recorded_before_multi_service_support_still_display(): void
    {
        $this->actingAs($this->admin)
            ->post('/transactions', $this->cart([$this->haircut->id => 2]))
            ->assertSessionHasNoErrors();
        $transaction = Transaction::firstOrFail();
        DB::table('transaction_services')->where('transaction_id', $transaction->id)->delete(); // old sales have no service rows

        $this->get('/transactions')->assertOk()->assertSee('Haircut');
        $this->get('/transactions/'.$transaction->id)->assertOk()->assertSee('Haircut');
    }

    public function test_the_detail_page_lists_every_selected_service(): void
    {
        $this->actingAs($this->admin)
            ->post('/transactions', $this->cart([$this->haircut->id => 1, $this->beardTrim->id => 2]))
            ->assertSessionHasNoErrors();
        $transaction = Transaction::firstOrFail();

        $this->get('/transactions/'.$transaction->id)->assertOk()
            ->assertSee('Selected services')->assertSee('Haircut')->assertSee('Beard Trim');
    }
}
