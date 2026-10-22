<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BarberAccountLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function managedBarber(): Barber
    {
        $user = User::factory()->create(['role' => 'barber']);

        return Barber::create(['user_id' => $user->id, 'first_name' => 'Layout', 'last_name' => 'Barber', 'hired_date' => today(), 'status' => 'active', 'availability' => 'available']);
    }

    public function test_table_has_only_edit_and_archive_account_actions_and_grouped_header_buttons(): void
    {
        $barber = $this->managedBarber();
        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('barbers.index'));
        $response->assertOk()->assertSee('barber-toolbar-actions')->assertSeeInOrder(['Archived barbers (0)', 'Add Barber'])
            ->assertSee('barber-account-actions')->assertSee('Edit details')->assertSee('Archive barber')
            ->assertDontSee('Deactivate account')->assertDontSee('Reset password')->assertDontSee('deactivate-'.$barber->id);
    }

    public function test_password_reset_is_inside_edit_details_and_retains_separate_protected_endpoint(): void
    {
        $barber = $this->managedBarber();
        $admin = User::factory()->create(['role' => 'admin']);
        $editUrl = route('barbers.index', ['edit' => $barber->id]);
        $this->actingAs($admin)->get($editUrl)->assertOk()->assertSee('Edit barber details')
            ->assertSee('barber-password-section')->assertSee('Reset password')->assertSee(route('barbers.password', $barber));
        $this->from($editUrl)->post(route('barbers.password', $barber), ['_modal' => 'edit-barber', 'password' => 'Temporary-password-123', 'password_confirmation' => 'Temporary-password-123'])
            ->assertRedirect($editUrl)->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Temporary-password-123', $barber->user->fresh()->password));
        $this->assertSame('barber', $barber->user->fresh()->role);
    }

    public function test_invalid_password_returns_to_edit_dialog_without_changing_password(): void
    {
        $barber = $this->managedBarber();
        $original = $barber->user->password;
        $editUrl = route('barbers.index', ['edit' => $barber->id]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->from($editUrl)
            ->post(route('barbers.password', $barber), ['_modal' => 'edit-barber', 'password' => 'short', 'password_confirmation' => 'different'])
            ->assertRedirect($editUrl)->assertSessionHasErrors('password')->assertSessionHasInput('_modal', 'edit-barber');
        $this->get($editUrl)->assertOk()->assertSee('barber-password-section')->assertSee('Reset password');
        $this->assertSame($original, $barber->user->fresh()->password);
    }
}
