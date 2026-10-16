<?php

namespace App\Console\Commands;

use App\Services\QuantityAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditQuantities extends Command
{
    protected $signature = 'shop:audit-data';

    protected $description = 'Read-only preflight for fractional quantities and duplicate payment records';

    public function handle(QuantityAudit $audit): int
    {
        $failed = false;
        foreach ($audit->issues() as $issue) {
            $failed = true;
            $this->warn(json_encode($issue, JSON_UNESCAPED_SLASHES));
        }
        foreach (['transaction_id', 'gcash_reference_no'] as $column) {
            $duplicates = DB::table('payments')->whereNotNull($column)->select($column)
                ->groupBy($column)->havingRaw('COUNT(*) > 1')->get();
            foreach ($duplicates as $duplicate) {
                $failed = true;
                // Report IDs, not sensitive reference numbers or customer data.
                $ids = DB::table('payments')->where($column, $duplicate->$column)->pluck('id');
                $this->warn('Duplicate '.$column.' on payment IDs: '.$ids->implode(', '));
            }
        }
        $this->info($failed ? 'BLOCKED: owner reconciliation required. No data changed.' : 'PASS: quantities are integral and payments have no duplicate references/transaction links. No data changed.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
