<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_services_are_seeded(): void
    {
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class])
            ->assertSuccessful();

        $this->assertGreaterThan(0, DB::table('services')->count());
    }

    public function test_seeders_never_overwrite_existing_prices_or_create_default_login(): void
    {
        $service = Service::create(['name' => 'Shave', 'price' => '175.51', 'duration_minutes' => 45]);
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class])->assertSuccessful();
        $this->assertSame('175.51', $service->fresh()->price);
        $this->assertSame(45, $service->fresh()->duration_minutes);
        $this->assertSame(0, User::count());
    }
}
