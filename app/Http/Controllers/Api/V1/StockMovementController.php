<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $request->query('branch_id', $user->branch_id);

        $query = StockMovement::with([
            'product:id,name,generic_name,sku',
            'batch:id,batch_number,expiry_date',
            'user:id,name,email',
        ])
        ->where('branch_id', $branchId);

        if ($productId = $request->query('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($batchId = $request->query('batch_id')) {
            $query->where('product_batch_id', $batchId);
        }

        if ($type = $request->query('movement_type')) {
            $query->where('movement_type', $type);
        }

        if ($from = $request->query('from_date')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to_date')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $movements = $query->latest('created_at')->paginate(30);

        return response()->json([
            'status' => 'success',
            'data' => $movements,
        ]);
    }
}
