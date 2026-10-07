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
        Schema::create('items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('category');
            $table->string('size');
            $table->string('color');
            $table->string('status', 50)->default('available');
            $table->decimal('rental_price', 10, 2)->default(0.00);
            $table->jsonb('custom_fields')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'idx_items_tenant_status');
        });

        DB::statement("ALTER TABLE items ADD CONSTRAINT check_item_status CHECK (status IN ('available', 'booked', 'out_with_customer', 'cleaning', 'maintenance'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
