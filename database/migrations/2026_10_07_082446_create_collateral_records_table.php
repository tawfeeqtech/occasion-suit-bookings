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
        Schema::create('collateral_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('status', 50)->default('held'); // 'held', 'released'
            $table->text('notes')->nullable();
            $table->timestampTz('held_at')->useCurrent();
            $table->timestampTz('released_at')->nullable();
            $table->foreignUuid('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'booking_id']);
        });

        DB::statement("ALTER TABLE collateral_records ADD CONSTRAINT check_collateral_status CHECK (status IN ('held', 'released'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collateral_records');
    }
};
