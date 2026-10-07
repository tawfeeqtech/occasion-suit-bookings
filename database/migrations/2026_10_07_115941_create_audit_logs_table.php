<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('actor_type', 50); // 'user' | 'system'
            $table->string('actor_id', 255);
            $table->string('action', 100);
            $table->string('entity_type', 100);
            $table->uuid('entity_id')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->string('ip_address', 45)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at'], 'idx_audit_logs_tenant_created');
            $table->index(['tenant_id', 'actor_id'], 'idx_audit_logs_actor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
