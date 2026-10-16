<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarberNavigationCleanupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $barberUser;

    private Barber $barber;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->barberUser = User::factory()->create(['role' => 'barber']);
        $this->barber = Barber::create(['user_id' => $this->barberUser->id, 'first_name' => 'Navigation', 'last_name' => 'Barber', 'hired_date' => today(), 'status' => 'active', 'availability' => 'available']);
    }

    public function test_barber_menu_omits_my_earnings_but_retains_queue_and_attendance(): void
    {
        $response = $this->actingAs($this->barberUser)->get('/dashboard');
        $response->assertOk()->assertDontSee('My Earnings')->assertSee('My Queue')->assertSee('Attendance');
        $response->assertDontSee('href="'.route('earnings').'"', false);
        $response->assertSee('href="'.route('transactions.index').'"', false);
        $response->assertSee('href="'.route('attendance.index').'"', false);
    }

    public function test_dashboard_has_no_duplicate_queue_or_attendance_quick_links(): void
    {
        $response = $this->actingAs($this->barberUser)->get('/dashboard');
        $response->assertOk()->assertDontSee('My Attendance');
        $this->assertSame(1, substr_count($response->getContent(), '>My Queue</a>'));
        $this->assertSame(1, substr_count($response->getContent(), 'href="'.route('transactions.index').'"'));
        $this->assertSame(1, substr_count($response->getContent(), 'href="'.route('attendance.index').'"'));
    }

    public function test_barber_earnings_remain_on_dashboard_and_reset_returns_to_dashboard(): void
    {
        $transaction = Transaction::create(['barber_id' => $this->barber->id, 'total_amount' => 200, 'barber_share' => 100, 'shop_share' => 100, 'status' => 'completed', 'transaction_date' => now()]);
        Payment::create(['transaction_id' => $transaction->id, 'payment_method' => 'cash', 'verification_status' => 'verified', 'amount' => 200]);
        $this->actingAs($this->barberUser)->get('/dashboard')
            ->assertOk()->assertViewHas('todayEarnings', 100)->assertViewHas('totalEarnings', 100)
            ->assertSee("Today's Earnings", false)->assertSee('Total Earnings')
            ->assertSee('Daily &amp; Monthly Sales Breakdown', false)
            ->assertSee('href="'.route('dashboard').'">Reset</a>', false);
    }

    public function test_queue_and_attendance_still_open_from_barber_navigation(): void
    {
        foreach (['/transactions', '/attendance', '/history', '/profile'] as $url) {
            $this->actingAs($this->barberUser)->get($url)->assertOk()->assertDontSee('My Earnings');
        }
    }

    public function test_admin_dashboard_navigation_is_kept_and_quick_shortcuts_are_removed(): void
    {
        $this->actingAs($this->admin)->get('/dashboard')->assertOk()
            ->assertSee('Point of Sale')->assertSee('Queue / Transactions')
            ->assertDontSee('Open POS')->assertDontSee('Open queue (');
    }

    public function test_old_earnings_bookmark_still_resolves_without_a_menu_entry(): void
    {
        $this->actingAs($this->barberUser)->get('/earnings')->assertOk()->assertSee('Total Earnings')->assertDontSee('My Earnings')->assertDontSee('My Attendance');
        $this->actingAs($this->admin)->get('/earnings')->assertForbidden();
    }
}
