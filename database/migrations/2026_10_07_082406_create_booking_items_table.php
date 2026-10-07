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
        Schema::create('booking_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('rental_price', 10, 2)->default(0.00);
            $table->string('inspection_status', 50)->default('clean_pass');
            $table->decimal('penalty_fee', 10, 2)->default(0.00);
            $table->text('penalty_reason')->nullable();
            $table->boolean('is_waived')->default(false);
            $table->text('damage_notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'item_id'], 'idx_booking_items_tenant_item');
            $table->index(['tenant_id', 'booking_id']);
        });

        DB::statement("ALTER TABLE booking_items ADD CONSTRAINT check_booking_item_inspection CHECK (inspection_status IN ('clean_pass', 'damaged', 'missing'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_items');
    }
};
