<?php

namespace Tests\Unit\Database;

use App\Database\StrictMySqlGrammar;
use Illuminate\Database\MySqlConnection;
use PHPUnit\Framework\TestCase;

class StrictMySqlGrammarTest extends TestCase
{
    private function connection(): MySqlConnection
    {
        $connection = new MySqlConnection(null);
        $connection->setQueryGrammar(new StrictMySqlGrammar($connection));

        return $connection;
    }

    public function test_legacy_payment_duplicate_checks_compile_without_select_star(): void
    {
        $connection = $this->connection();
        foreach (['transaction_id', 'gcash_reference_no'] as $column) {
            $query = $connection->table('payments')->whereNotNull($column)->groupBy($column)->havingRaw('COUNT(*) > 1');
            $sql = $connection->getQueryGrammar()->compileExists($query);
            $this->assertSame('select exists(select 1 from `payments` where `'.$column.'` is not null group by `'.$column.'` having COUNT(*) > 1) as `exists`', $sql);
            $this->assertNull($query->columns);
        }
    }

    public function test_grouped_exists_preserves_filters_having_and_bindings(): void
    {
        $connection = $this->connection();
        $query = $connection->table('payments')->where('verification_status', 'verified')->groupBy('transaction_id')->havingRaw('COUNT(*) > ?', [1]);
        $sql = $connection->getQueryGrammar()->compileExists($query);
        $this->assertSame('select exists(select 1 from `payments` where `verification_status` = ? group by `transaction_id` having COUNT(*) > ?) as `exists`', $sql);
        $this->assertSame(['verified', 1], $query->getBindings());
        $this->assertNull($query->columns);
    }

    public function test_explicit_grouped_projection_is_retained_for_having_aliases(): void
    {
        $connection = $this->connection();
        $query = $connection->table('payments')->selectRaw('transaction_id, COUNT(*) AS item_count')->groupBy('transaction_id')->having('item_count', '>', 1);
        $this->assertStringContainsString('select transaction_id, COUNT(*) AS item_count', $connection->getQueryGrammar()->compileExists($query));
        $this->assertSame([1], $query->getBindings());
    }

    public function test_normal_exists_and_union_projections_are_not_rewritten(): void
    {
        $connection = $this->connection();
        $query = $connection->table('payments')->where('verification_status', 'verified');
        $this->assertSame('select exists(select * from `payments` where `verification_status` = ?) as `exists`', $connection->getQueryGrammar()->compileExists($query));
        $union = $connection->table('payments')->groupBy('transaction_id')->union($connection->table('payments'));
        $this->assertStringContainsString('select * from `payments` group by', $connection->getQueryGrammar()->compileExists($union));
    }
}
