<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['cash_received', 'change_amount'] as $column) {
            if (DB::table('payments')->where($column, '>', '99999999.99')->orWhere($column, '<', 0)->exists()) {
                throw new RuntimeException('Payment precision migration blocked: '.$column.' exceeds DECIMAL(10,2). Reconcile with the owner; no values were truncated.');
            }
        }
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('cash_received', 10, 2)->nullable()->change();
            $table->decimal('change_amount', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Forward-only financial precision migration. Restore a verified backup.');
    }
};
