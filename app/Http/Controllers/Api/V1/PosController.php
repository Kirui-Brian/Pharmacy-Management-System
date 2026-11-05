<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Services\PosService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosController extends Controller
{
    protected PosService $posService;

    public function __construct(PosService $posService)
    {
        $this->posService = $posService;
    }

    /**
     * Fast medicine search optimized for POS cashier counter (supports barcode scanning).
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $request->query('branch_id', $user->branch_id);
        $search = $request->query('q');

        $query = Product::where('pharmacy_id', $user->pharmacy_id)
            ->where('is_active', true)
            ->with(['category:id,name', 'unit:id,name,short_name'])
            ->withSum([
                'batches as branch_stock' => function ($q) use ($branchId) {
                    $q->where('status', 'active')
                        ->where('expiry_date', '>', now()->toDateString());
                    if ($branchId) {
                        $q->where('branch_id', $branchId);
                    }
                }
            ], 'quantity_remaining');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('barcode', $search)
                    ->orWhere('sku', 'like', "{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('generic_name', 'like', "%{$search}%")
                    ->orWhere('brand_name', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('name')->take(30)->get();

        return response()->json([
            'status' => 'success',
            'data' => $products,
        ]);
    }

    /**
     * Execute POS Sale checkout with FEFO stock allocation and split payment support.
     */
    public function checkout(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'prescription_id' => ['nullable', 'exists:prescriptions,id'],
            'discount_type' => ['nullable', 'in:none,percentage,fixed'],
            'discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'pharmacist_override' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_selling_price' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_method' => ['required', 'in:cash,mpesa,card,bank_transfer,credit'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.amount_tendered' => ['nullable', 'numeric', 'min:0'],
            'payments.*.reference_number' => ['nullable', 'string', 'max:100'],
            'payments.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $sale = $this->posService->createSale($validated, $user);

        return response()->json([
            'status' => 'success',
            'message' => "Sale completed successfully. Receipt: #{$sale->receipt_number}",
            'data' => $sale,
        ], 201);
    }

    /**
     * Retrieve sale receipt data for printing or viewing.
     */
    public function getReceipt(Request $request, Sale $sale): JsonResponse
    {
        $user = $request->user();
        if ($sale->pharmacy_id !== $user->pharmacy_id) {
            abort(403);
        }

        $sale->load([
            'pharmacy',
            'branch',
            'cashier:id,name',
            'customer',
            'prescription',
            'items.product.unit',
            'items.batch',
            'payments.receiver:id,name'
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $sale,
        ]);
    }

    /**
     * Download or stream PDF receipt (thermal 80mm format or A4).
     */
    public function downloadPdfReceipt(Request $request, Sale $sale)
    {
        $user = $request->user();
        if ($sale->pharmacy_id !== $user->pharmacy_id) {
            abort(403);
        }

        $sale->load([
            'pharmacy',
            'branch',
            'cashier:id,name',
            'customer',
            'items.product.unit',
            'payments'
        ]);

        $format = $request->query('format', 'thermal'); // 'thermal' or 'a4'

        $pdf = Pdf::loadView('receipts.thermal', [
            'sale' => $sale,
            'isCopy' => $request->boolean('is_copy', false),
        ]);

        if ($format === 'thermal') {
            // 80mm width paper size
            $pdf->setPaper([0, 0, 226.77, 600], 'portrait');
        } else {
            $pdf->setPaper('a4', 'portrait');
        }

        return $pdf->stream("Receipt_{$sale->receipt_number}.pdf");
    }

    /**
     * Process return/refund.
     */
    public function processReturn(Request $request, Sale $sale): JsonResponse
    {
        $user = $request->user();
        if ($sale->pharmacy_id !== $user->pharmacy_id) {
            abort(403);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'refund_method' => ['required', 'in:cash,mpesa,credit_balance,none'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'exists:sale_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.condition' => ['required', 'in:resellable,damaged,expired'],
        ]);

        $return = $this->posService->processReturn(
            $sale,
            $validated['items'],
            $validated['reason'],
            $validated['refund_method'],
            $user
        );

        return response()->json([
            'status' => 'success',
            'message' => "Return processed successfully. Return #{$return->return_number}",
            'data' => $return,
        ]);
    }

    /**
     * Recent sales list for cashier counter shift.
     */
    public function recentSales(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $request->query('branch_id', $user->branch_id);

        $query = Sale::where('pharmacy_id', $user->pharmacy_id)
            ->with(['customer:id,name,phone', 'cashier:id,name', 'payments', 'items.product:id,name']);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $sales = $query->latest('sale_date')->take(20)->get();

        return response()->json([
            'status' => 'success',
            'data' => $sales,
        ]);
    }
}
