<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\BookingItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingItem extends Model
{
    /** @use HasFactory<BookingItemFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'booking_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'booking_id',
        'item_id',
        'rental_price',
        'inspection_status',
        'penalty_fee',
        'penalty_reason',
        'is_waived',
        'damage_notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rental_price' => 'decimal:2',
            'penalty_fee' => 'decimal:2',
            'is_waived' => 'boolean',
        ];
    }

    /**
     * Get the booking that owns this booking item.
     *
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    /**
     * Get the inventory item for this booking item.
     *
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
