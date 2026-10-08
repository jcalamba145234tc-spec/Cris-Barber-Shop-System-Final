<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('dashboard'));
    }

    public function test_login_page_has_password_visibility_toggle(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200)
            ->assertSee('data-password-toggle')
            ->assertSee('password');
    }

    public function test_barber_dashboard_loads_without_sql_groupby_error(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Barber',
            'email' => 'jane@example.com',
            'role' => 'barber',
        ]);

        $barber = Barber::create([
            'user_id' => $user->id,
            'first_name' => 'Jane',
            'last_name' => 'Barber',
            'hired_date' => today(),
            'status' => 'active',
        ]);

        $transactionId = (string) Str::uuid();
        DB::table('transactions')->insert([
            'id' => $transactionId,
            'barber_id' => $barber->id,
            'total_amount' => 100.00,
            'barber_share' => 50.00,
            'shop_share' => 50.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payments')->insert([
            'id' => (string) Str::uuid(),
            'transaction_id' => $transactionId,
            'payment_method' => 'cash',
            'gcash_reference_no' => null,
            'verification_status' => 'verified',
            'amount' => 100.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_barber_dashboard_shows_service_menu(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Barber',
            'email' => 'jane2@example.com',
            'role' => 'barber',
        ]);

        Barber::create([
            'user_id' => $user->id,
            'first_name' => 'Jane',
            'last_name' => 'Barber',
            'hired_date' => today(),
            'status' => 'active',
        ]);

        DB::table('services')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'Haircut',
            'price' => 250.00,
            'duration_minutes' => 30,
            'description' => 'Classic haircut',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200)
            ->assertSee('Service menu')
            ->assertSee('Haircut');
    }
}
