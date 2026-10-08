<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable()->index();
            $table->decimal('stock', 12, 3)->default(0);
            $table->string('unit', 30)->default('piece');
            $table->decimal('low_stock_threshold', 12, 3)->default(5);
            $table->decimal('cost', 10, 2)->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('active')->default(true)->index();
        });
        Schema::table('barbers', function (Blueprint $table) {
            $table->string('availability', 20)->default('available')->index();
        });
        Schema::create('service_products', function (Blueprint $table) {
            $table->foreignUuid('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->primary(['service_id', 'product_id']);
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignUuid('service_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_name')->nullable();
            $table->uuid('checkout_token')->nullable()->unique();
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status', 20)->default('completed')->index();
            $table->boolean('inventory_managed')->default(false);
            $table->timestamp('inventory_deducted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->softDeletes();
            $table->index(['barber_id', 'status']);
        });
        Schema::table('payments', function (Blueprint $table) {
            // Legacy reference data remains untouched; all new GCash references are DB-unique.
            $table->string('gcash_reference_key', 30)->nullable()->unique();
        });
        Schema::create('transaction_products', function (Blueprint $table) {
            $table->foreignUuid('transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->primary(['transaction_id', 'product_id']);
        });
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('transaction_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->decimal('quantity', 12, 3);
            $table->decimal('balance', 12, 3);
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->unique(['transaction_id', 'product_id', 'type']);
        });
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('barber_id')->constrained()->restrictOnDelete();
            $table->date('date')->index();
            $table->dateTime('time_in')->nullable();
            $table->dateTime('time_out')->nullable();
            $table->string('status', 20)->default('present');
            $table->timestamps();
            $table->unique(['barber_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('transaction_products');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['gcash_reference_key']);
            $table->dropColumn('gcash_reference_key');
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['checkout_token']);
            $table->dropIndex(['status']);
            $table->dropForeign(['service_id']);
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['barber_id', 'status']);
            $table->dropColumn(['service_id', 'customer_id', 'service_name', 'checkout_token', 'unit_price', 'quantity', 'status', 'inventory_managed', 'inventory_deducted_at', 'completed_at', 'deleted_at']);
        });
        Schema::dropIfExists('service_products');
        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['active']);
            $table->dropColumn('active');
        });
        Schema::table('barbers', function (Blueprint $table) {
            $table->dropIndex(['availability']);
            $table->dropColumn('availability');
        });
        Schema::dropIfExists('products');
        Schema::dropIfExists('customers');
    }
};
