<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryDisplayPrecisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_and_threshold_display_whole_numbers_in_inventory_list_and_details(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::create(['name' => 'Hair color', 'stock' => 50, 'low_stock_threshold' => 5, 'unit' => 'piece', 'active' => true]);
        $this->actingAs($admin)->get('/inventory')->assertOk()->assertSee('50 piece')->assertSee('<td>5</td>', false)->assertDontSee('50.000 piece');
        $this->get('/inventory/'.$product->id)->assertOk()->assertSee('50 piece')->assertSee('<dd>5</dd>', false);
        $this->get('/inventory/'.$product->id.'/edit')->assertOk()->assertSee('50 piece');
    }

    public function test_formatting_does_not_change_stored_stock_threshold_or_low_stock_logic(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        // Emulate an unmigrated legacy database without changing any real data.
        DB::unprepared('DROP TRIGGER whole_products_insert');
        DB::unprepared('DROP TRIGGER whole_products_update');
        $product = Product::create(['name' => 'Wax', 'stock' => '1.235', 'low_stock_threshold' => '1.234', 'unit' => 'portion', 'active' => true]);
        $this->actingAs($admin)->get('/inventory')->assertOk()->assertSee('1.235 (legacy — review required) portion')->assertSee('In Stock');
        $this->get('/inventory/'.$product->id.'/edit')->assertOk()->assertSee('value="1.234"', false);
        $this->assertSame('1.235', $product->fresh()->stock);
        $this->assertSame('1.234', $product->fresh()->low_stock_threshold);
        $this->assertSame('In Stock', $product->fresh()->stock_status);
    }

    public function test_zero_and_low_stock_keep_correct_status_with_whole_number_display(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Product::create(['name' => 'Empty', 'stock' => 0, 'low_stock_threshold' => 5, 'unit' => 'piece', 'active' => true]);
        Product::create(['name' => 'Low', 'stock' => 2, 'low_stock_threshold' => 5, 'unit' => 'portion', 'active' => true]);
        $this->actingAs($admin)->get('/inventory')->assertOk()->assertSee('0 piece')->assertSee('2 portion')->assertSee('Out of Stock')->assertSee('Low Stock');
    }
}
