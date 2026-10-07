<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('booking_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('type', 50); // 'advance', 'final_payment', 'penalty'
            $table->string('method', 50); // 'cash', 'palpay', 'jawwal_pay', 'bank_transfer'
            $table->string('reference_number')->nullable();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'booking_id']);
        });

        DB::statement("ALTER TABLE booking_payments ADD CONSTRAINT check_payment_type CHECK (type IN ('advance', 'final_payment', 'penalty'))");
        DB::statement("ALTER TABLE booking_payments ADD CONSTRAINT check_payment_method CHECK (method IN ('cash', 'palpay', 'jawwal_pay', 'bank_transfer'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_payments');
    }
};
