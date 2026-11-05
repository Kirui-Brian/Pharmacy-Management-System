<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SupplierInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierInvoiceController extends Controller
{
    /**
     * List supplier invoices with payment statuses and due dates.
     */
    public function index(Request $request): JsonResponse
    {
        $pharmacyId = $request->user()->pharmacy_id;
        $branchId = $request->query('branch_id', $request->user()->branch_id);

        $query = SupplierInvoice::where('pharmacy_id', $pharmacyId)
            ->with(['supplier:id,name,phone', 'goodsReceivedNote:id,grn_number', 'purchaseOrder:id,po_number', 'branch:id,name']);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->query('supplier_id'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->query('payment_status'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('overdue') && $request->boolean('overdue')) {
            $query->where('balance_due', '>', 0)
                  ->where('due_date', '<', now()->toDateString());
        }

        $invoices = $query->orderBy('due_date', 'asc')->paginate($request->query('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $invoices,
        ]);
    }

    /**
     * View supplier invoice details and payment history.
     */
    public function show(Request $request, SupplierInvoice $supplierInvoice): JsonResponse
    {
        if ($supplierInvoice->pharmacy_id !== $request->user()->pharmacy_id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized access.'], 403);
        }

        $supplierInvoice->load([
            'supplier',
            'goodsReceivedNote.items.product',
            'purchaseOrder',
            'payments.payer:id,name',
            'branch:id,name',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $supplierInvoice,
        ]);
    }
}
