<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\User;
use App\Rules\ShopEmailDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ShopEmailDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_shop_domain_is_required_without_suffix_or_subdomain_bypasses(): void
    {
        foreach (['jake@crisbarber.com', 'jake@CRISBARBER.COM'] as $email) {
            $this->assertTrue(Validator::make(['email' => $email], ['email' => ['email', new ShopEmailDomain]])->passes());
        }
        foreach (['jake@gmail.com', 'jake@sub.crisbarber.com', 'jake@crisbarber.com.evil.com', 'jake@fakecrisbarber.com', 'jake@crisbarberXcom', 'crisbarber.com', 'jake@crisbarber.com '] as $email) {
            $this->assertFalse(Validator::make(['email' => $email], ['email' => ['email', new ShopEmailDomain]])->passes());
        }
    }

    public function test_admin_creation_generates_local_login_and_ignores_submitted_email(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $data = ['first_name' => 'Jake', 'last_name' => 'Calamba', 'email' => 'attacker@gmail.com', 'role' => 'barber', 'password' => 'Valid-password-123', 'password_confirmation' => 'Valid-password-123'];
        $this->actingAs($admin)->post('/accounts', $data)->assertRedirect('/barbers')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['name' => 'Jake Calamba', 'email' => 'jake.calamba@crisbarbershop.local', 'role' => 'barber']);
        $this->assertDatabaseMissing('users', ['email' => 'attacker@gmail.com']);
        $this->post('/accounts', $data)->assertRedirect('/barbers')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'jake.calamba2@crisbarbershop.local']);
    }

    public function test_owner_cli_rejects_external_admin_email(): void
    {
        $this->artisan('shop:admin', ['email' => 'owner@gmail.com', '--name' => 'Owner'])
            ->expectsQuestion('Password (minimum 12 characters)', 'Owner-secret-123')
            ->expectsQuestion('Confirm password', 'Owner-secret-123')->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'owner@gmail.com']);
    }

    public function test_both_roles_must_use_shop_domain_when_changing_email(): void
    {
        foreach (['admin', 'barber'] as $role) {
            $user = User::factory()->create(['role' => $role, 'email' => $role.'@legacy.test']);
            if ($role === 'barber') {
                Barber::create(['user_id' => $user->id, 'first_name' => 'Legacy', 'last_name' => 'Barber', 'hired_date' => today()]);
            }
            $data = ['name' => $user->name, 'email' => $role.'@gmail.com'];
            $this->actingAs($user)->put('/profile', $data)->assertSessionHasErrors('email');
            $this->assertSame($role.'@legacy.test', $user->fresh()->email);
            $data['email'] = $role.'@crisbarber.com';
            $this->put('/profile', $data)->assertRedirect('/profile')->assertSessionHasNoErrors();
            $this->assertSame($data['email'], $user->fresh()->email);
            $this->put('/profile', ['name' => $user->name, 'email' => $role.'@legacy.test'])->assertSessionHasErrors('email');
        }
    }

    public function test_legacy_users_can_still_login_and_change_password_without_changing_email(): void
    {
        foreach (['admin', 'barber'] as $role) {
            $user = User::factory()->create(['role' => $role, 'email' => $role.'@gmail.com', 'password' => 'Old-password-123']);
            if ($role === 'barber') {
                Barber::create(['user_id' => $user->id, 'first_name' => 'Legacy', 'last_name' => 'Barber', 'hired_date' => today()]);
            }
            $this->post('/login', ['email' => $user->email, 'password' => 'Old-password-123'])->assertRedirect('/dashboard');
            $this->put('/profile', ['name' => $user->name, 'email' => $user->email, 'password' => 'New-password-123', 'password_confirmation' => 'New-password-123'])->assertRedirect('/profile')->assertSessionHasNoErrors();
            $this->assertTrue(Hash::check('New-password-123', $user->fresh()->password));
            $this->assertSame($role.'@gmail.com', $user->fresh()->email);
            $this->post('/logout');
        }
    }

    public function test_barber_cannot_create_accounts_and_customer_emails_are_unrestricted(): void
    {
        $barber = User::factory()->create(['role' => 'barber']);
        $this->actingAs($barber)->post('/accounts', ['email' => 'other@crisbarber.com'])->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/customers', ['name' => 'Customer', 'email' => 'customer@gmail.com'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', ['email' => 'customer@gmail.com']);
        $this->get('/accounts/create')->assertRedirect(route('barbers.index', ['add' => 1]));
        $this->get('/barbers?add=1')->assertSee('First Name')->assertSee('Last Name')->assertSee('readonly', false);
    }
}
