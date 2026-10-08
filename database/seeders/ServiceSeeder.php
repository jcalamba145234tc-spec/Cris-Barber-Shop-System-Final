<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            ['name' => 'Haircut / Hot Towel', 'price' => 150.00, 'duration_minutes' => 30],
            ['name' => 'Shave', 'price' => 150.00, 'duration_minutes' => 20],
            ['name' => 'Shampoo w/ Scalp Massage', 'price' => 100.00, 'duration_minutes' => 15],
            ['name' => 'Ear Cleaning', 'price' => 150.00, 'duration_minutes' => 15],
            ['name' => 'Hair Dye / Haircut', 'price' => 400.00, 'duration_minutes' => 60],
            ['name' => 'Hot Towel w/ Scalp Massage', 'price' => 200.00, 'duration_minutes' => 25],
            ['name' => 'Hair Straightening', 'price' => 400.00, 'duration_minutes' => 90],
            ['name' => 'Highlights', 'price' => 400.00, 'duration_minutes' => 60],
            ['name' => 'Massage Half Body', 'price' => 350.00, 'duration_minutes' => 30],
            ['name' => 'Massage Whole Body', 'price' => 600.00, 'duration_minutes' => 60],
            ['name' => 'Complete Service', 'price' => 1050.00, 'duration_minutes' => 120],
        ];

        foreach ($services as $service) {
            $existingService = DB::table('services')->where('name', $service['name'])->first();

            if ($existingService) {
                DB::table('services')->where('id', $existingService->id)->update([
                    'price' => $service['price'],
                    'duration_minutes' => $service['duration_minutes'],
                    'updated_at' => now(),
                ]);

                continue;
            }

            DB::table('services')->insert($service + [
                'id' => (string) Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
