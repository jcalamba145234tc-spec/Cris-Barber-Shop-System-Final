<?php

// database/migrations/2026_01_01_000001_create_barbershop_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The application users table is created by Laravel's base migration.
        // 1. Barbers Table
        Schema::create('barbers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('phone_number', 20)->nullable();
            $table->date('hired_date');
            $table->enum('status', ['active', 'inactive', 'on_leave'])->default('active');
            $table->timestamps();
        });

        // 3. Services Table
        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('duration_minutes')->default(30);
            $table->timestamps();
        });

        // 4. Transactions & Revenue Split Table
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('barber_id')->constrained('barbers');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('barber_share', 10, 2); // 50% para sa Barbero
            $table->decimal('shop_share', 10, 2);   // 50% para sa Shop Owner
            $table->timestamps();
        });

        // 5. Payments Table (Cash ug GCash verification)
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transaction_id')->constrained('transactions')->onDelete('cascade');
            $table->enum('payment_method', ['cash', 'gcash']);
            $table->string('gcash_reference_no', 30)->nullable();
            $table->enum('verification_status', ['pending', 'verified', 'flagged'])->default('verified');
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('services');
        Schema::dropIfExists('barbers');
    }
};
