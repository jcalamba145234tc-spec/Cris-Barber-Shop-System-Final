<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('inventory_movements', fn (Blueprint $table) => $table->uuid('restock_token')->nullable()->unique());
    }

    public function down(): void
    {
        throw new RuntimeException('Forward-only history-preserving migration. Restore a verified backup for recovery.');
    }
};
