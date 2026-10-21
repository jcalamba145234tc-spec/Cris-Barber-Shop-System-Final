<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerProfileNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewing_a_customer_always_shows_a_back_link_to_the_customer_list(): void
    {
        $customer = Customer::create(['name' => 'Miguel Santos']);
        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('customers.show', $customer));

        $response->assertOk()->assertSee('Back to customers')
            ->assertSee('class="button secondary-button" href="'.route('customers.index').'"', false)
            ->assertSee('Edit customer')->assertSee($customer->name);
        $this->get(route('customers.index'))->assertOk()->assertSee($customer->name);
    }
}
