<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CheckAvailabilityRequest;
use App\Services\AvailabilityService;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

class AvailabilityController extends Controller
{
    public function __construct(
        protected AvailabilityService $availabilityService
    ) {}

    /**
     * Check availability for selected items over date range.
     */
    public function check(CheckAvailabilityRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->availabilityService->checkAvailability(
            $validated['item_ids'],
            $validated['pickup_date'],
            $validated['return_date'],
            TenantContext::getTenantId()
        );

        return response()->json($result);
    }
}
