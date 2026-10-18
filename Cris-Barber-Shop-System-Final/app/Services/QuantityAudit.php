<?php

namespace App\Services;

use App\Support\WholeQuantity;
use Illuminate\Support\Facades\DB;

class QuantityAudit
{
    public const COLUMNS = [
        'products' => ['stock', 'low_stock_threshold'],
        'service_products' => ['quantity'],
        'transaction_products' => ['quantity'],
        'inventory_movements' => ['quantity', 'balance'],
        'transactions' => ['quantity'],
    ];

    public function issues(): \Generator
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach (DB::table($table)->cursor() as $row) {
                foreach ($columns as $column) {
                    if (! WholeQuantity::isWhole($row->$column, $table === 'inventory_movements' && $column === 'quantity')) {
                        yield ['table' => $table, 'record' => $row->id ?? ($row->service_id ?? $row->transaction_id).'/'.$row->product_id,
                            'column' => $column, 'value' => (string) $row->$column];
                    }
                }
            }
        }
    }
}
