<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BookingConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateBookingRequest;
use App\Http\Resources\Api\V1\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(
        protected BookingService $bookingService
    ) {}

    /**
     * Store a new booking with ACID transaction and row locking.
     */
    public function store(CreateBookingRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['tenant_id'] = TenantContext::getTenantId();

        try {
            $booking = $this->bookingService->createBooking(
                $validated,
                $request->user()
            );

            return (new BookingResource($booking))
                ->response()
                ->setStatusCode(201);
        } catch (BookingConflictException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'conflicts' => $e->conflicts,
            ], 409);
        }
    }

    /**
     * Get today's pickups and scheduled returns for the tenant.
     */
    public function today(Request $request): JsonResponse
    {
        $today = Carbon::today()->toDateString();

        $pickups = Booking::with(['bookingItems.item', 'collateralRecord'])
            ->whereDate('pickup_date', $today)
            ->whereIn('status', ['active', 'confirmed'])
            ->orderBy('pickup_date', 'asc')
            ->get();

        $returns = Booking::with(['bookingItems.item', 'collateralRecord'])
            ->whereDate('return_date', $today)
            ->whereIn('status', ['active', 'out_with_customer'])
            ->orderBy('return_date', 'asc')
            ->get();

        return response()->json([
            'date' => $today,
            'pickups_today_count' => $pickups->count(),
            'pickups_today' => BookingResource::collection($pickups),
            'returns_today_count' => $returns->count(),
            'returns_today' => BookingResource::collection($returns),
        ]);
    }
}
