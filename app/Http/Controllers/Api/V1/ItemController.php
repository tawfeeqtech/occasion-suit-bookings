<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateItemRequest;
use App\Http\Resources\Api\V1\ItemResource;
use App\Models\Item;
use App\Services\AuditService;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{
    /**
     * Create an inventory item for the authenticated user's tenant.
     */
    public function store(CreateItemRequest $request, AuditService $auditService): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();
        $actor = $request->user();
        $attributes = $request->validated();

        $item = DB::transaction(function () use ($attributes, $tenantId, $actor, $auditService, $request): Item {
            $item = Item::create([
                ...$attributes,
                'tenant_id' => $tenantId,
            ]);

            $auditService->log(
                action: 'item_created',
                entityType: 'Item',
                entityId: $item->id,
                metadata: [
                    'name' => $item->name,
                    'category' => $item->category,
                    'size' => $item->size,
                    'color' => $item->color,
                    'status' => $item->status,
                    'rental_price' => (float) $item->rental_price,
                    'custom_fields' => $item->custom_fields,
                ],
                actor: $actor,
                ip: $request->ip(),
                tenantId: $tenantId
            );

            return $item;
        });

        return (new ItemResource($item))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display a listing of inventory items scoped to the authenticated tenant.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Item::query();

        // Search filter (name, color, category)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('color', 'ilike', "%{$search}%")
                    ->orWhere('category', 'ilike', "%{$search}%");
            });
        }

        // Status filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Category filter
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        // Size filter
        if ($size = $request->input('size')) {
            $query->where('size', $size);
        }

        $items = $query->orderBy('name', 'asc')->paginate(50);

        return ItemResource::collection($items);
    }
}
