<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompactFilterLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_toolbar_keeps_accessible_autocomplete_and_normal_search(): void
    {
        Customer::create(['name' => 'Miguel Santos']);
        Customer::create(['name' => 'Other Customer']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/customers?q=sant')
            ->assertOk()->assertSee('class="customer-search-toolbar"', false)
            ->assertSee('role="search"', false)->assertSee('role="combobox"', false)
            ->assertSee('Search customers by name, email or phone')
            ->assertSee('Miguel Santos')->assertDontSee('Other Customer');
    }

    public function test_queue_advanced_filters_are_closed_until_applied_and_preserve_selected_values(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $response = $this->get('/transactions');
        $response->assertOk()->assertSee('class="queue-filter-toolbar"', false)
            ->assertSee('class="queue-advanced-filters" >', false)
            ->assertSee('name="barber_id"', false)->assertSee('Apply filters');
        $response = $this->get('/transactions?from=2026-10-01&payment_method=cash&status=completed');
        $response->assertOk()->assertSee('class="queue-advanced-filters"  open ', false)
            ->assertSee('2 active')->assertSee('value="2026-10-01"', false)
            ->assertSee('selected value="cash"', false)->assertSee('selected value="completed"', false);
    }

    public function test_barber_queue_omits_redundant_barber_picker_and_keeps_all_other_filters(): void
    {
        $user = User::factory()->create(['role' => 'barber']);
        Barber::create(['user_id' => $user->id, 'first_name' => 'Layout', 'last_name' => 'Barber', 'hired_date' => today(), 'status' => 'active', 'availability' => 'available']);
        $response = $this->actingAs($user)->get('/transactions');
        $response->assertOk()->assertSee('My Queue')->assertDontSee('name="barber_id"', false);
        foreach (['q', 'status', 'from', 'to', 'service_id', 'payment_method', 'claim_status'] as $name) {
            $response->assertSee('name="'.$name.'"', false);
        }
        $this->get('/customers')->assertForbidden();
    }
}
