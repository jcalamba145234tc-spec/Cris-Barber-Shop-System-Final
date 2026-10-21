<?php

namespace Tests\Feature;

use App\Database\StrictMySqlGrammar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StrictMySqlCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_mysql_connections_use_strict_grouped_exists_without_opening_a_socket(): void
    {
        // A grammar is configured before any PDO query, including migrate's
        // preflight checks. This also exercises wiring in the SQLite test suite.
        $connection = DB::connection('mysql');
        $this->assertInstanceOf(StrictMySqlGrammar::class, $connection->getQueryGrammar());
        $this->assertTrue($connection->getConfig('strict'));
    }

    public function test_duplicate_detection_still_blocks_duplicates_under_full_group_by(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Real MySQL/MariaDB connection required.');
        }
        $connection = DB::connection();
        $this->assertStringContainsString('ONLY_FULL_GROUP_BY', $connection->selectOne('SELECT @@SESSION.sql_mode AS sql_mode')->sql_mode);
        $connection->statement('CREATE TEMPORARY TABLE strict_exists_probe (id INTEGER NOT NULL, group_key VARCHAR(30) NULL)');
        try {
            $connection->table('strict_exists_probe')->insert([
                ['id' => 1, 'group_key' => 'duplicate'], ['id' => 2, 'group_key' => 'duplicate'],
                ['id' => 3, 'group_key' => 'unique'], ['id' => 4, 'group_key' => null],
            ]);
            $query = $connection->table('strict_exists_probe')->whereNotNull('group_key')->groupBy('group_key')->havingRaw('COUNT(*) > ?', [1]);
            $this->assertTrue($query->exists());
            $this->assertFalse((clone $query)->where('group_key', 'unique')->exists());
            $this->assertSame(4, $connection->table('strict_exists_probe')->count());
            $this->assertNull($query->columns);
        } finally {
            $connection->statement('DROP TEMPORARY TABLE IF EXISTS strict_exists_probe');
        }
    }
}
