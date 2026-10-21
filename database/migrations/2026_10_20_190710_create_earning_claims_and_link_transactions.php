<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('earning_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('barber_id')->constrained()->restrictOnDelete();
            $table->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('transaction_count');
            $table->timestamp('confirmed_at');
            $table->uuid('claim_token')->unique();
            $table->string('fingerprint', 64);
            $table->timestamps();
            $table->index(['barber_id', 'confirmed_at']);
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignUuid('claim_id')->nullable()->constrained('earning_claims')->restrictOnDelete();
            $table->index(['barber_id', 'claim_id', 'status'], 'transactions_unclaimed_index');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Forward-only payout ledger. Restore a verified backup instead of deleting payout records.');
    }
};
