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
        Schema::create('item_maintenance', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignUuid('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->string('status', 50)->default('cleaning'); // 'cleaning', 'maintenance', 'completed'
            $table->timestampTz('started_at')->useCurrent();
            $table->timestampTz('expected_ready_at');
            $table->timestampTz('actual_ready_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'expected_ready_at'], 'idx_item_maintenance_ready');
            $table->index(['tenant_id', 'item_id']);
        });

        DB::statement("ALTER TABLE item_maintenance ADD CONSTRAINT check_maintenance_status CHECK (status IN ('cleaning', 'maintenance', 'completed'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_maintenance');
    }
};
