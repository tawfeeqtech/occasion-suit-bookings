<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'items';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'category',
        'size',
        'color',
        'status',
        'rental_price',
        'custom_fields',
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
            'custom_fields' => 'array',
        ];
    }

    /**
     * Get the booking items associated with this item.
     *
     * @return HasMany<BookingItem, $this>
     */
    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class, 'item_id');
    }

    /**
     * Get the maintenance/cleaning records for this item.
     *
     * @return HasMany<ItemMaintenance, $this>
     */
    public function maintenances(): HasMany
    {
        return $this->hasMany(ItemMaintenance::class, 'item_id');
    }
}
