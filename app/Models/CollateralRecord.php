<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CollateralRecordFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollateralRecord extends Model
{
    /** @use HasFactory<CollateralRecordFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'collateral_records';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'booking_id',
        'status',
        'notes',
        'held_at',
        'released_at',
        'released_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'held_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    /**
     * Get the booking associated with this collateral record.
     *
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    /**
     * Get the user who released the collateral.
     *
     * @return BelongsTo<User, $this>
     */
    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
