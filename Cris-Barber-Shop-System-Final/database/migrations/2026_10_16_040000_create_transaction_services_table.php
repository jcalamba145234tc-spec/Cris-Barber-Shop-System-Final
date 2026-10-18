<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_services', function (Blueprint $table) {
            $table->foreignUuid('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained()->restrictOnDelete();
            $table->string('service_name');
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->primary(['transaction_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_services');
    }
};
