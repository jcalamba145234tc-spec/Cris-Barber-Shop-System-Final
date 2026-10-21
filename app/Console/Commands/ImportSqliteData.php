<?php

namespace App\Console\Commands;

use App\Services\QuantityAudit;
use App\Support\Money;
use App\Support\WholeQuantity;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use RuntimeException;
use Throwable;

class ImportSqliteData extends Command
{
    protected $signature = 'shop:import-sqlite {source : Absolute path to a stopped, backed-up SQLite database} {--dry-run : Validate without writing}';

    protected $description = 'Import business data into an empty migrated MySQL database without changing IDs, payments, or stock.';

    private const TABLES = ['users', 'barbers', 'customers', 'products', 'services', 'earning_claims', 'transactions', 'payments',
        'service_products', 'transaction_services', 'transaction_products', 'inventory_movements', 'attendance'];

    public function handle(): int
    {
        $source = null;
        try {
            if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                throw new RuntimeException('The destination must use MySQL or MariaDB. No data was changed.');
            }
            $path = realpath($this->argument('source'));
            if (! $path || ! is_file($path) || ! is_readable($path)) {
                throw new RuntimeException('Source file does not exist or is unreadable.');
            }
            $source = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $source->exec('PRAGMA query_only = ON');
            $source->beginTransaction();
            $tables = $source->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
            foreach (['users', 'barbers', 'transactions', 'payments'] as $required) {
                if (! in_array($required, $tables, true)) {
                    throw new RuntimeException('Not a shop backup: missing '.$required.'.');
                }
            }
            foreach (self::TABLES as $table) {
                if (! Schema::hasTable($table) || ($table === 'transactions' && ! Schema::hasColumn('transactions', 'claim_id'))) {
                    throw new RuntimeException('Run php artisan migrate on the destination first (without --seed).');
                }
                if (DB::table($table)->exists()) {
                    throw new RuntimeException('Destination business tables must be empty; '.$table.' contains data. Never merge or overwrite a live database.');
                }
            }
            // Preflight before any writes. No fractional quantities or out-of-range money may be coerced by MySQL.
            foreach (self::TABLES as $table) {
                if (! in_array($table, $tables, true)) {
                    continue;
                }
                $rows = $source->query('SELECT * FROM "'.$table.'"');
                $count = 0;
                while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                    $count++;
                    foreach (QuantityAudit::COLUMNS[$table] ?? [] as $column) {
                        if (isset($row[$column]) && ! WholeQuantity::isWhole($row[$column], $table === 'inventory_movements' && $column === 'quantity')) {
                            throw new RuntimeException($table.'.'.$column.' has a non-integral or out-of-range quantity. Obtain owner reconciliation; nothing was imported.');
                        }
                    }
                    foreach (['total_amount', 'barber_share', 'shop_share', 'amount', 'price', 'unit_price', 'cost', 'cash_received', 'change_amount'] as $column) {
                        if (isset($row[$column]) && Money::cents((string) $row[$column]) > 9999999999) {
                            throw new RuntimeException($table.'.'.$column.' exceeds DECIMAL(10,2). Nothing was imported.');
                        }
                    }
                    if ($table === 'transactions' && Money::cents((string) $row['total_amount']) !== Money::cents((string) $row['barber_share']) + Money::cents((string) $row['shop_share'])) {
                        throw new RuntimeException('A transaction has a split mismatch. Obtain owner reconciliation; no amounts were recalculated.');
                    }
                }
                $this->line($table.': '.$count.' rows validated');
            }
            if ($this->option('dry-run')) {
                $source->rollBack();
                $this->info('Preflight passed. No rows written. Foreign keys and uniqueness will be checked during the transactional import.');

                return self::SUCCESS;
            }
            DB::transaction(function () use ($source, $tables): void {
                foreach (self::TABLES as $table) {
                    if (! in_array($table, $tables, true)) {
                        continue;
                    }
                    $columns = array_flip(Schema::getColumnListing($table));
                    $rows = $source->query('SELECT * FROM "'.$table.'"');
                    while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                        DB::table($table)->insert(array_intersect_key($row, $columns));
                    }
                    if (DB::table($table)->count() !== (int) $source->query('SELECT COUNT(*) FROM "'.$table.'"')->fetchColumn()) {
                        throw new RuntimeException('Row count mismatch: '.$table.'. Import rolled back.');
                    }
                }
            });
            $source->rollBack();
            $this->info('Import complete. IDs, archived links, financial snapshots and stock were preserved. Source was not modified.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if ($source?->inTransaction()) {
                $source->rollBack();
            }
            report($exception);
            $reason = $exception instanceof QueryException
                ? 'A destination foreign key, unique key, or required-field constraint failed. Ask the owner to review the protected application log.'
                : $exception->getMessage();
            $this->error('Import failed; no business rows committed. '.$reason);

            return self::FAILURE;
        }
    }
}
