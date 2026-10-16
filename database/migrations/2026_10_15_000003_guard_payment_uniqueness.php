<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['transaction_id', 'gcash_reference_no'] as $column) {
            if (DB::table('payments')->whereNotNull($column)->groupBy($column)->havingRaw('COUNT(*) > 1')->exists()) {
                throw new RuntimeException('Payment uniqueness migration blocked. Run shop:audit-data; owner must reconcile duplicate '.$column.'. No payments changed.');
            }
        }
        Schema::table('payments', function (Blueprint $table) {
            $table->unique('transaction_id');
            $table->unique('gcash_reference_no');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Forward-only financial integrity migration. Restore a verified backup for recovery.');
    }
};
