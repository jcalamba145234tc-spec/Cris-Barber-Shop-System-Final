<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarberStatusDropdownTest extends TestCase
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
        $this->barber = Barber::create(['user_id' => $this->barberUser->id, 'first_name' => 'Dropdown', 'last_name' => 'Barber', 'hired_date' => today(), 'status' => 'active', 'availability' => 'available']);
    }

    public function test_barber_page_has_one_compact_auto_saving_status_dropdown(): void
    {
        $this->actingAs($this->admin)->get('/barbers')->assertOk()
            ->assertSee('name="barber_state"', false)
            ->assertSee('data-autosave-control', false)
            ->assertSee('data-save-feedback', false)
            ->assertDontSee('aria-label="Manual availability"', false)
            ->assertDontSee('aria-label="Barber status"', false);
    }

    public function test_each_dropdown_choice_updates_the_existing_status_and_availability(): void
    {
        foreach ([
            'unavailable' => ['active', 'unavailable'],
            'inactive' => ['inactive', 'unavailable'],
            'on_leave' => ['on_leave', 'unavailable'],
            'available' => ['active', 'available'],
        ] as $choice => [$status, $availability]) {
            $this->actingAs($this->admin)->patch('/barbers/'.$this->barber->id.'/status', ['barber_state' => $choice])->assertSessionHasNoErrors();
            $this->assertSame($status, $this->barber->fresh()->status);
            $this->assertSame($availability, $this->barber->fresh()->availability);
        }
    }

    public function test_invalid_dropdown_values_cannot_change_barber_data(): void
    {
        foreach (['busy', 'admin', '', 'unknown'] as $choice) {
            $this->actingAs($this->admin)->patch('/barbers/'.$this->barber->id.'/status', ['barber_state' => $choice])->assertSessionHasErrors('barber_state');
        }
        $this->assertSame('active', $this->barber->fresh()->status);
        $this->assertSame('available', $this->barber->fresh()->availability);
    }

    public function test_barbers_and_guests_cannot_use_the_admin_dropdown_endpoint(): void
    {
        $this->patch('/barbers/'.$this->barber->id.'/status', ['barber_state' => 'inactive'])->assertRedirect('/login');
        $this->actingAs($this->barberUser)->patch('/barbers/'.$this->barber->id.'/status', ['barber_state' => 'inactive'])->assertForbidden();
    }

    public function test_old_two_field_status_requests_remain_compatible(): void
    {
        $this->actingAs($this->admin)->patch('/barbers/'.$this->barber->id.'/status', ['status' => 'on_leave', 'availability' => 'available'])->assertSessionHasNoErrors();
        $this->assertSame('on_leave', $this->barber->fresh()->status);
        $this->assertSame('available', $this->barber->fresh()->availability);
        $this->get('/barbers')->assertOk()->assertSee('value="on_leave" selected', false);
    }

    public function test_conflicting_dropdown_and_old_fields_are_rejected(): void
    {
        $this->actingAs($this->admin)->patch('/barbers/'.$this->barber->id.'/status', ['barber_state' => 'available', 'status' => 'inactive', 'availability' => 'unavailable'])->assertSessionHasErrors(['status', 'availability']);
        $this->assertSame('active', $this->barber->fresh()->status);
    }

    public function test_available_choice_does_not_override_an_actual_busy_queue(): void
    {
        Transaction::create(['barber_id' => $this->barber->id, 'total_amount' => 150, 'barber_share' => 75, 'shop_share' => 75, 'status' => 'queued', 'transaction_date' => now()]);
        $this->actingAs($this->admin)->patch('/barbers/'.$this->barber->id.'/status', ['barber_state' => 'available'])->assertSessionHasNoErrors();
        $this->assertFalse(Barber::available()->whereKey($this->barber->id)->exists());
        $this->get('/barbers')->assertOk()->assertSee('Busy');
    }
}
