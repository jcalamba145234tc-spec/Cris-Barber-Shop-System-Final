<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Optional SAMPLE data: the shop's services (from ServiceSeeder) plus example inventory
 * and the example supplies each service uses.
 *
 * Run explicitly:  php artisan db:seed --class=InventorySampleSeeder
 *
 * Safe to repeat. It only ADDS what is missing and never changes or removes existing
 * products, stock, prices, or service requirements. All quantities are whole numbers;
 * prices and costs keep their decimals. The amounts below are examples only. Replace
 * them with the shop's real supplies and usage under Inventory and Services.
 */
class InventorySampleSeeder extends Seeder
{
    /** name, category, unit, stock, low-stock threshold, cost (₱), description */
    private const PRODUCTS = [
        ['Disposable Razor Blade', 'Shaving', 'piece', 200, 40, '3.00', 'Single-use blade for shaves.'],
        ['Aftershave Sachet', 'Grooming', 'sachet', 150, 30, '5.00', 'Aftershave provided by the shop.'],
        ['Powder Sachet', 'Grooming', 'sachet', 150, 30, '2.50', 'Neck and face powder provided by the shop.'],
        ['Neck Strip', 'Supplies', 'piece', 300, 50, '0.50', 'Disposable neck strip.'],
        ['Shampoo Sachet', 'Hair care', 'sachet', 200, 40, '6.00', 'Single-use shampoo sachet.'],
        ['Hair Dye Pack', 'Hair color', 'pack', 40, 10, '85.00', 'One application of hair dye.'],
        ['Hair Straightening Cream Pack', 'Hair treatment', 'pack', 25, 8, '120.00', 'One application of straightening cream.'],
        ['Highlight Bleach Pack', 'Hair color', 'pack', 25, 8, '95.00', 'One application of highlight bleach.'],
        ['Massage Oil Sachet', 'Massage', 'sachet', 120, 25, '10.00', 'Single-use massage oil sachet.'],
        ['Cotton Buds (pair)', 'Supplies', 'pair', 300, 60, '0.75', 'Cotton buds for ear cleaning.'],
    ];

    /** service name => [product name => whole-number quantity used per service] */
    private const REQUIREMENTS = [
        'Haircut / Hot Towel' => ['Neck Strip' => 1, 'Powder Sachet' => 1],
        'Shave' => ['Disposable Razor Blade' => 1, 'Aftershave Sachet' => 1, 'Neck Strip' => 1],
        'Shampoo w/ Scalp Massage' => ['Shampoo Sachet' => 1],
        'Ear Cleaning' => ['Cotton Buds (pair)' => 1],
        'Hair Dye / Haircut' => ['Hair Dye Pack' => 1, 'Neck Strip' => 1, 'Powder Sachet' => 1],
        'Hot Towel w/ Scalp Massage' => ['Massage Oil Sachet' => 1],
        'Hair Straightening' => ['Hair Straightening Cream Pack' => 1, 'Shampoo Sachet' => 1],
        'Highlights' => ['Highlight Bleach Pack' => 1, 'Neck Strip' => 1],
        'Massage Half Body' => ['Massage Oil Sachet' => 2],
        'Massage Whole Body' => ['Massage Oil Sachet' => 4],
        'Complete Service' => [
            'Disposable Razor Blade' => 1, 'Aftershave Sachet' => 1, 'Powder Sachet' => 1, 'Neck Strip' => 1,
            'Shampoo Sachet' => 1, 'Cotton Buds (pair)' => 1, 'Massage Oil Sachet' => 4,
        ],
    ];

    public function run(): void
    {
        // Adds any of the 11 menu services that are missing; existing prices are never overwritten.
        $this->call(ServiceSeeder::class);

        DB::transaction(function (): void {
            $now = now();

            foreach (self::PRODUCTS as [$name, $category, $unit, $stock, $threshold, $cost, $description]) {
                if (DB::table('products')->where('name', $name)->exists()) {
                    continue; // keep the owner's existing product, stock and cost untouched
                }

                $id = DB::table('products')->insertGetId([
                    'name' => $name, 'description' => $description, 'category' => $category, 'unit' => $unit,
                    'stock' => $stock, 'low_stock_threshold' => $threshold, 'cost' => $cost, 'active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);

                DB::table('inventory_movements')->insert([
                    'product_id' => $id, 'user_id' => null, 'type' => 'opening',
                    'quantity' => $stock, 'balance' => $stock, 'notes' => 'Sample opening stock',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            foreach (self::REQUIREMENTS as $serviceName => $items) {
                $service = DB::table('services')->where('name', $serviceName)->first();
                if (! $service || DB::table('service_products')->where('service_id', $service->id)->exists()) {
                    continue; // missing service, or the owner already configured its supplies
                }

                foreach ($items as $productName => $quantity) {
                    $productId = DB::table('products')->where('name', $productName)->value('id');
                    if ($productId) {
                        DB::table('service_products')->insert([
                            'service_id' => $service->id, 'product_id' => $productId, 'quantity' => $quantity,
                        ]);
                    }
                }
            }
        });
    }
}
