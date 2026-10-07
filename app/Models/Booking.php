<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'bookings';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'booking_number',
        'customer_name',
        'customer_phone',
        'pickup_date',
        'event_date',
        'return_date',
        'status',
        'total_fee',
        'advance_paid',
        'remaining_balance',
        'payment_method',
        'alterations_notes',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pickup_date' => 'date',
            'event_date' => 'date',
            'return_date' => 'date',
            'total_fee' => 'decimal:2',
            'advance_paid' => 'decimal:2',
            'remaining_balance' => 'decimal:2',
        ];
    }

    /**
     * Get the items in this booking.
     *
     * @return HasMany<BookingItem, $this>
     */
    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class, 'booking_id');
    }

    /**
     * Get the inventory items through booking items.
     *
     * @return HasManyThrough<Item, BookingItem, $this>
     */
    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(Item::class, BookingItem::class, 'booking_id', 'id', 'id', 'item_id');
    }

    /**
     * Get the payments recorded for this booking.
     *
     * @return HasMany<BookingPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class, 'booking_id');
    }

    /**
     * Get the collateral record for this booking.
     *
     * @return HasOne<CollateralRecord, $this>
     */
    public function collateralRecord(): HasOne
    {
        return $this->hasOne(CollateralRecord::class, 'booking_id');
    }

    /**
     * Get the user who created this booking.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
