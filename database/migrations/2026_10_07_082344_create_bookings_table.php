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
        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('booking_number', 50);
            $table->string('customer_name');
            $table->string('customer_phone', 50);
            $table->date('pickup_date');
            $table->date('event_date')->nullable();
            $table->date('return_date');
            $table->string('status', 50)->default('active');
            $table->decimal('total_fee', 10, 2);
            $table->decimal('advance_paid', 10, 2)->default(0.00);
            $table->decimal('remaining_balance', 10, 2);
            $table->string('payment_method', 100);
            $table->text('alterations_notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'pickup_date', 'return_date'], 'idx_bookings_tenant_dates');
            $table->index(['tenant_id', 'booking_number']);
        });

        DB::statement("ALTER TABLE bookings ADD CONSTRAINT check_booking_status CHECK (status IN ('active', 'completed', 'overdue', 'damage_pending', 'cancelled'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
