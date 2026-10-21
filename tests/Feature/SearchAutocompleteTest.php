<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchAutocompleteTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name, array $attributes = []): Product
    {
        return Product::create($attributes + ['name' => $name, 'stock' => 3, 'low_stock_threshold' => 5, 'unit' => 'bottle', 'category' => 'Hair care', 'active' => true]);
    }

    private function service(string $name, array $attributes = []): Service
    {
        return Service::create($attributes + ['name' => $name, 'price' => '150.00', 'duration_minutes' => 30, 'active' => true]);
    }

    public function test_product_suggestions_are_partial_case_insensitive_and_respect_all_filters(): void
    {
        $owner = User::factory()->create(['role' => 'admin']);
        $product = $this->product('Professional Shampoo Coconut 500ml');
        $this->product('Shampoo Large', ['stock' => 20]);
        $this->product('Shampoo Inactive', ['active' => false]);
        $this->product('Shampoo Empty', ['stock' => 0]);
        $this->product('Shampoo Other', ['category' => 'Other']);
        $this->actingAs($owner)->getJson('/inventory?q=SHAM&category=Hair%20care&stock_status=low&active=1')
            ->assertOk()->assertJsonCount(1, 'suggestions')
            ->assertJsonPath('suggestions.0.label', 'Professional Shampoo Coconut 500ml')
            ->assertJsonPath('suggestions.0.value', 'Professional Shampoo Coconut 500ml')
            ->assertJsonPath('suggestions.0.detail', 'Hair care · 3 bottle · Low Stock');
        $this->assertSame('3.000', $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->get('/inventory?q=SHAM&stock_status=low&active=1')->assertOk()->assertSee($product->name)->assertSee('Low Stock');
    }

    public function test_customer_suggestions_search_name_email_and_phone_but_never_archived_customers(): void
    {
        $customer = Customer::create(['name' => 'Miguel Santos', 'phone' => '09123456789', 'email' => 'Miguel@example.test']);
        $archived = Customer::create(['name' => 'Miguel Archived', 'phone' => '09123456789', 'email' => 'archived@example.test']);
        $archived->delete();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['SANT', 'MIGUEL@EXAMPLE', '345678'] as $term) {
            $this->getJson('/customers?'.http_build_query(['q' => $term]))->assertOk()->assertJsonCount(1, 'suggestions')
                ->assertJsonPath('suggestions.0.label', $customer->name)->assertJsonPath('suggestions.0.value', $customer->name);
            $this->get('/customers?'.http_build_query(['q' => $term]))->assertOk()->assertSee($customer->name)->assertDontSee('Miguel Archived');
        }
        $this->assertDatabaseCount('customers', 2);
        $this->assertSoftDeleted($archived);
    }

    public function test_service_suggestions_respect_status_and_include_price_and_duration(): void
    {
        $this->service('Haircut / Hot Towel');
        $this->service('Haircut Old', ['active' => false]);
        $this->service('Massage');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/services?q=HAIR&active=1')
            ->assertOk()->assertJsonCount(1, 'suggestions')->assertJsonPath('suggestions.0.label', 'Haircut / Hot Towel')
            ->assertJsonPath('suggestions.0.detail', '₱150.00 · 30 min · Active');
        $this->get('/services?q=HAIR&active=1')->assertOk()->assertSee('Haircut / Hot Towel')->assertDontSee('Haircut Old');
    }

    public function test_each_dropdown_is_bounded_sorted_and_empty_until_a_query_is_supplied(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        for ($i = 11; $i >= 0; $i--) {
            $name = 'Hair '.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $this->product($name);
            $this->service($name);
            Customer::create(['name' => $name]);
        }
        foreach (['inventory', 'customers', 'services'] as $path) {
            $this->getJson('/'.$path.'?q=hair')->assertOk()->assertJsonCount(8, 'suggestions')
                ->assertJsonPath('suggestions.0.label', 'Hair 00')->assertJsonPath('suggestions.7.label', 'Hair 07');
            $this->getJson('/'.$path)->assertOk()->assertExactJson(['suggestions' => []]);
            $this->getJson('/'.$path.'?q=%20%20')->assertOk()->assertExactJson(['suggestions' => []]);
            $this->getJson('/'.$path.'?q=not-found')->assertOk()->assertExactJson(['suggestions' => []]);
        }
    }

    public function test_zero_is_a_real_search_term_and_queries_are_bound_not_executed_as_sql(): void
    {
        $this->product('0 Percent Shampoo');
        $this->product('Other');
        $this->service('0 Percent Haircut');
        $this->service('Other');
        Customer::create(['name' => 'Customer 0']);
        Customer::create(['name' => 'Other']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['inventory', 'customers', 'services'] as $path) {
            $this->getJson('/'.$path.'?q=0')->assertOk()->assertJsonCount(1, 'suggestions');
            $this->getJson('/'.$path.'?'.http_build_query(['q' => "' OR 1=1 --"]))->assertOk()->assertExactJson(['suggestions' => []]);
        }
    }

    public function test_suggestion_endpoints_keep_guest_and_barber_access_denied(): void
    {
        foreach (['inventory', 'customers', 'services'] as $path) {
            $this->getJson('/'.$path.'?q=hair')->assertUnauthorized();
        }
        $this->actingAs(User::factory()->create(['role' => 'barber']));
        foreach (['inventory', 'customers', 'services'] as $path) {
            $this->getJson('/'.$path.'?q=hair')->assertForbidden();
        }
    }

    public function test_invalid_queries_and_filters_use_existing_validation(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['inventory', 'customers', 'services'] as $path) {
            $this->getJson('/'.$path.'?q='.str_repeat('x', 101))->assertUnprocessable()->assertJsonValidationErrors('q');
            $this->getJson('/'.$path.'?q[]=hair')->assertUnprocessable()->assertJsonValidationErrors('q');
        }
        $this->getJson('/inventory?q=hair&stock_status=wrong')->assertUnprocessable()->assertJsonValidationErrors('stock_status');
        $this->getJson('/services?q=hair&active=wrong')->assertUnprocessable()->assertJsonValidationErrors('active');
    }

    public function test_html_forms_are_accessible_and_legacy_ajax_still_receives_html(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['inventory', 'customers', 'services'] as $path) {
            $this->get('/'.$path)->assertOk()->assertSee('role="combobox"', false)
                ->assertSee('aria-autocomplete="list"', false)->assertSee('role="listbox"', false)->assertSee('maxlength="100"', false);
        }
        $this->get('/inventory?q=sham', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')->assertSee('id="inventory-results"', false);
    }

    public function test_names_with_markup_are_escaped_in_html_and_kept_as_text_in_json(): void
    {
        $name = 'Hair <img src=x onerror=alert(1)>';
        $this->product($name);
        $this->service($name);
        Customer::create(['name' => $name]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['inventory', 'customers', 'services'] as $path) {
            $this->getJson('/'.$path.'?q=hair')->assertOk()->assertJsonPath('suggestions.0.label', $name);
            $this->get('/'.$path.'?q=hair')->assertOk()->assertSee(e($name), false)->assertDontSee($name, false);
        }
    }
}
