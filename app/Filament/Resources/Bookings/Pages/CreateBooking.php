<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Services\BookingService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    /**
     * Create the booking using the domain BookingService to enforce pessimistic locking.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(BookingService::class)->createBooking($data, auth()->user());
    }
}
