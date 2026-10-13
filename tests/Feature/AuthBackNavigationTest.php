<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthBackNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_protected_pages_are_not_cached(): void
    {
        $this->get('/login')->assertOk()->assertHeader('Pragma', 'no-cache')->assertHeader('Expires', '0')->assertSee('event.persisted', false);
        $this->assertStringContainsString('no-store', $this->get('/login')->headers->get('Cache-Control'));
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get('/dashboard');
        $response->assertOk()->assertSessionHas('auth.last_page', '/dashboard');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_back_to_login_returns_admin_to_most_recent_page_including_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->get('/inventory?stock_status=low')->assertOk()->assertSessionHas('auth.last_page', '/inventory?stock_status=low');
        $this->get('/login')->assertRedirect('/inventory?stock_status=low');
        $this->get('/pos')->assertOk();
        $this->get('/login')->assertRedirect('/pos');
    }

    public function test_barber_returns_to_own_current_page_not_sign_in(): void
    {
        $barber = User::factory()->create(['role' => 'barber']);
        $this->actingAs($barber)->get('/profile')->assertOk();
        $this->get('/login')->assertRedirect('/profile');
        $this->get('/inventory')->assertForbidden();
        $this->get('/login')->assertRedirect('/profile');
    }

    public function test_logout_clears_last_page_and_back_cannot_access_protected_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/inventory')->assertOk();
        $this->post('/logout')->assertRedirect('/login')->assertSessionMissing('auth.last_page');
        $this->assertGuest();
        $this->get('/inventory')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }

    public function test_redirect_falls_back_to_dashboard_without_a_safe_last_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/login')->assertRedirect('/dashboard');
        foreach (['https://evil.example', '//evil.example', '/\\evil.example'] as $path) {
            $this->withSession(['auth.last_page' => $path])->get('/login')->assertRedirect('/dashboard');
        }
    }
}
