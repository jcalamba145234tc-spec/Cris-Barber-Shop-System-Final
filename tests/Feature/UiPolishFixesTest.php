<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Barber;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Readable hours, readable dates, and clear barber earnings wording. */
class UiPolishFixesTest extends TestCase
{
    use RefreshDatabase;

    private function barberUser(): array
    {
        $user = User::factory()->create(['role' => 'barber']);
        $barber = Barber::create(['user_id' => $user->id, 'first_name' => 'Polish', 'last_name' => 'Barber', 'hired_date' => today(), 'status' => 'active', 'availability' => 'available']);

        return [$user, $barber];
    }

    public function test_hours_label_shows_hours_and_minutes_while_the_numeric_value_is_unchanged(): void
    {
        $short = new Attendance(['time_in' => '2026-10-21 10:02:00', 'time_out' => '2026-10-21 10:33:00']);
        $long = new Attendance(['time_in' => '2026-10-21 09:00:00', 'time_out' => '2026-10-21 17:05:00']);
        $open = new Attendance(['time_in' => '2026-10-21 09:00:00']);

        $this->assertSame('0h 31m', $short->hours_label);
        $this->assertSame('8h 5m', $long->hours_label);
        $this->assertNull($open->hours_label);
        $this->assertEquals(0.52, $short->hours); // reports and calculations still get the number
    }

    public function test_the_barber_attendance_page_shows_hours_and_minutes_not_decimals(): void
    {
        [$user, $barber] = $this->barberUser();
        Attendance::create(['barber_id' => $barber->id, 'date' => today(), 'time_in' => today()->setTime(10, 2), 'time_out' => today()->setTime(10, 33), 'status' => 'present']);

        $this->actingAs($user)->get('/attendance')->assertOk()
            ->assertSee('0h 31m')->assertDontSee('0.52');
    }

    public function test_the_barber_dashboard_uses_clear_earnings_wording_without_a_duplicate_card(): void
    {
        [$user, $barber] = $this->barberUser();
        $transaction = Transaction::create(['barber_id' => $barber->id, 'total_amount' => 200, 'barber_share' => 100, 'shop_share' => 100, 'status' => 'completed', 'transaction_date' => '2026-10-02 10:00:00']);
        Payment::create(['transaction_id' => $transaction->id, 'payment_method' => 'cash', 'verification_status' => 'verified', 'amount' => 200]);

        $this->actingAs($user)->get('/dashboard?month=2026-10')->assertOk()
            ->assertSee('<h2>My earnings</h2>', false)
            ->assertSee('Total of my completed services')
            ->assertSee('My earnings in October 2026')
            ->assertSee('Oct 2, 2026')
            ->assertDontSee('2026-10-02')
            ->assertDontSee('Selected month earnings')
            ->assertDontSee('Net Barber Share');

        // With a single date chosen, the month total is a separate figure and is shown too.
        $this->get('/dashboard?month=2026-10&date=2026-10-02')->assertOk()
            ->assertSee('My earnings on Oct 2, 2026')
            ->assertSee('My earnings in October 2026');
    }

    public function test_customer_and_inventory_pages_show_readable_dates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, $barber] = $this->barberUser();
        $customer = Customer::create(['name' => 'Date Customer', 'phone' => '09123456789']);
        Transaction::create(['barber_id' => $barber->id, 'customer_id' => $customer->id, 'total_amount' => 150, 'barber_share' => 75, 'shop_share' => 75, 'status' => 'completed', 'transaction_date' => '2026-10-02 10:00:00']);
        $product = Product::create(['name' => 'Date Wax', 'stock' => 5, 'unit' => 'piece', 'low_stock_threshold' => 2, 'active' => true]);
        $movement = InventoryMovement::create(['product_id' => $product->id, 'type' => 'restock', 'quantity' => 5, 'balance' => 5]);

        $this->actingAs($admin)->get(route('customers.show', $customer))->assertOk()
            ->assertSee('Oct 2, 2026')->assertDontSee('2026-10-02 10:00:00');
        $this->get(route('inventory.show', $product))->assertOk()
            ->assertSee($product->created_at->format('M j, Y'))
            ->assertDontSee($movement->created_at->format('Y-m-d H:i:s'));
    }
}
