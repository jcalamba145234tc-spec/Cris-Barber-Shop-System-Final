<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginDashboardLandingTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'password' => 'Landing-password-123']);
        if ($role === 'barber') {
            Barber::create(['user_id' => $user->id, 'first_name' => 'Landing', 'last_name' => 'Barber', 'hired_date' => today(), 'status' => 'active']);
        }

        return $user;
    }

    public function test_admin_sign_in_opens_dashboard_even_after_a_protected_deep_link(): void
    {
        $user = $this->account('admin');
        $this->get('/inventory?stock_status=low')->assertRedirect('/login');
        $this->withSession(['auth.last_page' => '/transactions'])->post('/login', [
            'email' => $user->email, 'password' => 'Landing-password-123',
        ])->assertRedirect('/dashboard')->assertSessionMissing('url.intended')->assertSessionMissing('auth.last_page');
        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertOk()->assertSee('Admin dashboard');
    }

    public function test_barber_sign_in_opens_own_dashboard_instead_of_the_previous_profile_page(): void
    {
        $user = $this->account('barber');
        $this->get('/profile')->assertRedirect('/login');
        $this->withSession(['auth.last_page' => '/attendance'])->post('/login', [
            'email' => $user->email, 'password' => 'Landing-password-123',
        ])->assertRedirect('/dashboard')->assertSessionMissing('url.intended')->assertSessionMissing('auth.last_page');
        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertOk()->assertSee('Barber dashboard');
        $this->get('/inventory')->assertForbidden();
    }

    public function test_stale_external_intended_urls_cannot_override_the_dashboard_landing(): void
    {
        $user = $this->account('admin');
        $this->withSession(['url.intended' => 'https://example.test/old-page', 'auth.last_page' => '/pos'])
            ->post('/login', ['email' => $user->email, 'password' => 'Landing-password-123'])
            ->assertRedirect('/dashboard')->assertSessionMissing('url.intended')->assertSessionMissing('auth.last_page');
    }

    public function test_failed_sign_in_stays_unauthenticated_and_a_valid_retry_lands_on_dashboard(): void
    {
        $user = $this->account('admin');
        $this->from('/login')->withSession(['url.intended' => '/transactions'])->post('/login', [
            'email' => $user->email, 'password' => 'Wrong-password',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'Landing-password-123'])
            ->assertRedirect('/dashboard')->assertSessionMissing('url.intended');
    }
}
