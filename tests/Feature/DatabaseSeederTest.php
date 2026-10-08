<?php

namespace Tests\Feature;

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
}
