<?php

namespace Tests\Feature;

use App\Support\WholeQuantity;
use Database\Seeders\InventorySampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventorySampleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_the_menu_services_inventory_and_service_supply_links(): void
    {
        $this->seed(InventorySampleSeeder::class);

        $this->assertSame(11, DB::table('services')->count());
        $this->assertSame(10, DB::table('products')->count());
        $this->assertSame(11, DB::table('services')->whereIn('id', DB::table('service_products')->pluck('service_id'))->count());
        $this->assertSame(10, DB::table('inventory_movements')->where('type', 'opening')->count());
        $this->assertSame('150.00', number_format((float) DB::table('services')->where('name', 'Shave')->value('price'), 2, '.', ''));
    }

    public function test_all_sample_quantities_are_whole_numbers_and_costs_keep_their_decimals(): void
    {
        $this->seed(InventorySampleSeeder::class);

        foreach (DB::table('products')->get() as $product) {
            $this->assertTrue(WholeQuantity::isWhole($product->stock), $product->name.' stock must be whole');
            $this->assertTrue(WholeQuantity::isWhole($product->low_stock_threshold), $product->name.' threshold must be whole');
            $this->assertGreaterThan((int) $product->low_stock_threshold, (int) $product->stock, $product->name.' should start above its low-stock level');
        }
        foreach (DB::table('service_products')->pluck('quantity') as $quantity) {
            $this->assertTrue(WholeQuantity::isWhole($quantity));
        }
        $this->assertEquals(2.5, (float) DB::table('products')->where('name', 'Powder Sachet')->value('cost'));
    }

    public function test_running_it_twice_does_not_duplicate_anything(): void
    {
        $this->seed(InventorySampleSeeder::class);
        $counts = [DB::table('services')->count(), DB::table('products')->count(), DB::table('service_products')->count(), DB::table('inventory_movements')->count()];

        $this->seed(InventorySampleSeeder::class);

        $this->assertSame($counts, [DB::table('services')->count(), DB::table('products')->count(), DB::table('service_products')->count(), DB::table('inventory_movements')->count()]);
    }

    public function test_it_never_changes_existing_products_prices_or_service_supplies(): void
    {
        $this->seed(\Database\Seeders\ServiceSeeder::class);
        DB::table('services')->where('name', 'Shave')->update(['price' => 175]);
        $now = now();
        DB::table('products')->insert(['name' => 'Powder Sachet', 'unit' => 'sachet', 'stock' => 7, 'low_stock_threshold' => 2, 'cost' => '9.99', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $ownerProduct = DB::table('products')->insertGetId(['name' => 'Owner Wax', 'unit' => 'piece', 'stock' => 12, 'low_stock_threshold' => 3, 'cost' => '20.00', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $haircut = DB::table('services')->where('name', 'Haircut / Hot Towel')->value('id');
        DB::table('service_products')->insert(['service_id' => $haircut, 'product_id' => $ownerProduct, 'quantity' => 1]);

        $this->seed(InventorySampleSeeder::class);

        $this->assertEquals(175, (float) DB::table('services')->where('name', 'Shave')->value('price'));
        $this->assertEquals(7, (int) DB::table('products')->where('name', 'Powder Sachet')->value('stock'));
        $this->assertEquals(9.99, (float) DB::table('products')->where('name', 'Powder Sachet')->value('cost'));
        $this->assertSame(1, DB::table('products')->where('name', 'Powder Sachet')->count());
        $this->assertSame([$ownerProduct], DB::table('service_products')->where('service_id', $haircut)->pluck('product_id')->all());
    }
}
