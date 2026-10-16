<?php

use App\Services\QuantityAudit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ALL data checks precede ALL DDL, important for MySQL's implicit DDL commits.
        foreach (app(QuantityAudit::class)->issues() as $issue) {
            throw new RuntimeException('Integer conversion blocked: '.json_encode($issue).'. Run shop:audit-data and obtain owner reconciliation. No quantities converted.');
        }
        foreach (QuantityAudit::COLUMNS as $table => $columns) {
            if ($table === 'transactions') {
                continue; // Already unsigned integer in the existing schema.
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
                foreach ($columns as $column) {
                    $definition = $blueprint->integer($column);
                    if ($table !== 'inventory_movements' || $column !== 'quantity') {
                        $definition->unsigned();
                    }
                    if ($table === 'products') {
                        $definition->default($column === 'stock' ? 0 : 5);
                    }
                    $definition->change();
                }
            });
        }
        // SQLite integer affinity alone accepts fractions; reject them explicitly.
        if (DB::getDriverName() === 'sqlite') {
            foreach (QuantityAudit::COLUMNS as $table => $columns) {
                $conditions = [];
                foreach ($columns as $column) {
                    $minimum = $table === 'inventory_movements' && $column === 'quantity' ? -999999999 : 0;
                    $conditions[] = "NEW.{$column} IS NULL OR NEW.{$column} != CAST(NEW.{$column} AS INTEGER) OR NEW.{$column} < {$minimum} OR NEW.{$column} > 999999999";
                }
                foreach (['INSERT', 'UPDATE'] as $operation) {
                    DB::unprepared('CREATE TRIGGER whole_'.$table.'_'.strtolower($operation).' BEFORE '.$operation.' ON '.$table.' WHEN '.implode(' OR ', $conditions)." BEGIN SELECT RAISE(ABORT, 'Inventory quantities must be whole numbers in range'); END");
                }
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Forward-only precision migration. Do not rollback accounting data.');
    }
};
