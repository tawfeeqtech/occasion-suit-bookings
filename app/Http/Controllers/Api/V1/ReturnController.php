<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProcessReturnRequest;
use App\Http\Resources\Api\V1\BookingResource;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Services\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReturnController extends Controller
{
    /**
     * Process return inspection for all booking items.
     */
    public function processReturn(ProcessReturnRequest $request, string $id, ReturnService $returnService): JsonResponse
    {
        $booking = Booking::findOrFail($id);

        $updatedBooking = $returnService->processReturn($booking, $request->validated(), $request->user());

        return response()->json([
            'message' => 'تمت معالجة الإرجاع بنجاح',
            'data' => new BookingResource($updatedBooking),
        ], Response::HTTP_OK);
    }

    /**
     * Release customer's held national ID collateral record.
     */
    public function releaseCollateral(Request $request, string $id, ReturnService $returnService): JsonResponse
    {
        $booking = Booking::findOrFail($id);

        $collateral = $returnService->releaseCollateral($booking, $request->user(), $request->input('notes'));

        return response()->json([
            'message' => 'تم تسليم بطاقة الهوية بنجاح',
            'data' => $collateral,
        ], Response::HTTP_OK);
    }

    /**
     * Waive a penalty for a damaged/missing booking item (Shop Owner only).
     */
    public function waivePenalty(Request $request, string $bookingId, string $itemId, ReturnService $returnService): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $bookingItem = BookingItem::where('booking_id', $bookingId)
            ->where('id', $itemId)
            ->firstOrFail();

        $returnService->waivePenalty($bookingItem, $request->user(), $validated['reason']);

        return response()->json([
            'message' => 'تم إعفاء الغرامة بنجاح',
            'data' => $bookingItem->fresh(),
        ], Response::HTTP_OK);
    }

    /**
     * Record a penalty payment for a booking.
     */
    public function payPenalty(Request $request, string $id, ReturnService $returnService): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'in:cash,palpay,jawwal_pay,bank_transfer'],
            'reference_number' => ['nullable', 'string', 'max:100'],
        ]);

        $booking = Booking::findOrFail($id);

        $payment = $returnService->payPenalty(
            $booking,
            (float) $validated['amount'],
            $validated['payment_method'],
            $request->user(),
            $validated['reference_number'] ?? null
        );

        return response()->json([
            'message' => 'تم تسجيل سداد الغرامة بنجاح',
            'data' => [
                'payment' => $payment,
                'booking' => new BookingResource($booking->fresh()),
            ],
        ], Response::HTTP_OK);
    }
}
