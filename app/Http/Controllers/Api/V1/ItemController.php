<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ItemResource;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ItemController extends Controller
{
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
