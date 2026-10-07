<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    /**
     * Audit logs are append-only.
     *
     * @var string|null
     */
    public const UPDATED_AT = null;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'audit_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'actor_type',
        'actor_id',
        'action',
        'entity_type',
        'entity_id',
        'metadata',
        'ip_address',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Optional relationship to the user actor if actor_type is 'user'.
     *
     * @return BelongsTo<User, $this>
     */
    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Prevent updates to ensure audit immutability.
     *
     * @throws \RuntimeException
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \RuntimeException('Audit logs are immutable and cannot be updated.');
    }

    /**
     * Prevent deletions to ensure audit immutability.
     *
     * @throws \RuntimeException
     */
    public function delete(): ?bool
    {
        throw new \RuntimeException('Audit logs are immutable and cannot be deleted.');
    }
}
