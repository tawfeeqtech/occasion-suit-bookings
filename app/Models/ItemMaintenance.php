<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ItemMaintenanceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemMaintenance extends Model
{
    /** @use HasFactory<ItemMaintenanceFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'item_maintenance';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'item_id',
        'booking_id',
        'status',
        'started_at',
        'expected_ready_at',
        'actual_ready_at',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expected_ready_at' => 'datetime',
            'actual_ready_at' => 'datetime',
        ];
    }

    /**
     * Get the item associated with this maintenance record.
     *
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * Get the booking associated with this maintenance record (if any).
     *
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }
}
