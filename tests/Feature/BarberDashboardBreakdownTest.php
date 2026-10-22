<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BarberDashboardBreakdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_barber_dashboard_shows_daily_sales_breakdown_for_selected_month(): void
    {
        $user = User::factory()->create([
            'name' => 'Daniel Dave Soncio',
            'role' => 'barber',
        ]);

        $barberId = (string) Str::uuid();
        DB::table('barbers')->insert([
            'id' => $barberId,
            'user_id' => $user->id,
            'first_name' => 'Daniel',
            'last_name' => 'Soncio',
            'hired_date' => '2026-01-01',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tx1 = (string) Str::uuid();
        $tx2 = (string) Str::uuid();

        DB::table('transactions')->insert([
            ['id' => $tx1, 'barber_id' => $barberId, 'total_amount' => 1000.00, 'barber_share' => 500.00, 'shop_share' => 500.00, 'created_at' => '2026-10-01 09:00:00', 'updated_at' => '2026-10-01 09:00:00'],
            ['id' => $tx2, 'barber_id' => $barberId, 'total_amount' => 500.00, 'barber_share' => 250.00, 'shop_share' => 250.00, 'created_at' => '2026-10-02 10:00:00', 'updated_at' => '2026-10-02 10:00:00'],
        ]);

        DB::table('payments')->insert([
            ['id' => (string) Str::uuid(), 'transaction_id' => $tx1, 'payment_method' => 'cash', 'gcash_reference_no' => null, 'verification_status' => 'verified', 'amount' => 1000.00, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'transaction_id' => $tx2, 'payment_method' => 'gcash', 'gcash_reference_no' => 'GCASH-1', 'verification_status' => 'pending', 'amount' => 500.00, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($user)->get('/dashboard?month=2026-10');

        $response->assertStatus(200)
            ->assertSee('My earnings')
            ->assertSee('Total of my completed services')
            ->assertSee('My earnings in October 2026');
    }
}
