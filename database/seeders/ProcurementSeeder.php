<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProcurementService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ProcurementSeeder extends Seeder
{
    public function run(): void
    {
        $pharmacy = Pharmacy::first();
        if (!$pharmacy) {
            return;
        }

        $branch = Branch::where('pharmacy_id', $pharmacy->id)->first();
        $admin = User::where('pharmacy_id', $pharmacy->id)->first();
        $suppliers = Supplier::where('pharmacy_id', $pharmacy->id)->get();
        $products = Product::where('pharmacy_id', $pharmacy->id)->take(5)->get();

        if ($suppliers->isEmpty() || $products->isEmpty() || !$admin) {
            return;
        }

        $procurementService = app(ProcurementService::class);
        $supplier1 = $suppliers->first();
        $supplier2 = $suppliers->skip(1)->first() ?? $supplier1;

        // 1. Create a Purchase Order
        $poItems = [];
        foreach ($products->take(3) as $prod) {
            $poItems[] = [
                'product_id' => $prod->id,
                'quantity_ordered' => 100,
                'unit_cost' => $prod->purchase_price ?: 50.00,
                'discount_amount' => 0.00,
                'tax_amount' => 0.00,
            ];
        }

        $po = $procurementService->createPurchaseOrder([
            'supplier_id' => $supplier1->id,
            'branch_id' => $branch->id,
            'order_date' => Carbon::now()->subDays(5)->toDateString(),
            'expected_delivery_date' => Carbon::now()->addDays(2)->toDateString(),
            'shipping_cost' => 500.00,
            'status' => 'ordered',
            'notes' => 'Monthly bulk restocking order.',
            'items' => $poItems,
        ], $admin);

        // 2. Stock Reception via GRN for the first 2 items of the PO
        $grnItems = [];
        foreach (array_slice($poItems, 0, 2) as $index => $item) {
            $poItem = $po->items[$index];
            $grnItems[] = [
                'product_id' => $item['product_id'],
                'purchase_order_item_id' => $poItem->id,
                'batch_number' => 'SEEDED-GRN-' . rand(1000, 9999),
                'manufacturing_date' => Carbon::now()->subMonths(2)->toDateString(),
                'expiry_date' => Carbon::now()->addMonths(24)->toDateString(),
                'quantity_received' => 100,
                'unit_purchase_price' => $item['unit_cost'],
                'unit_selling_price' => $item['unit_cost'] * 1.35,
            ];
        }

        $grn = $procurementService->receiveStock([
            'supplier_id' => $supplier1->id,
            'branch_id' => $branch->id,
            'purchase_order_id' => $po->id,
            'delivery_note_number' => 'DN-LX-' . rand(10000, 99999),
            'supplier_invoice_number' => 'LX-INV-' . rand(20000, 99999),
            'received_date' => Carbon::now()->subDays(3)->toDateString(),
            'notes' => 'Shipment inspected and temperature log verified.',
            'items' => $grnItems,
        ], $admin);

        // 3. Record a partial payment for the generated invoice
        $invoice = $grn->supplierInvoices()->first();
        if ($invoice) {
            $paymentAmount = round($invoice->total_amount / 2, 2);
            $procurementService->recordSupplierPayment([
                'supplier_id' => $supplier1->id,
                'branch_id' => $branch->id,
                'supplier_invoice_id' => $invoice->id,
                'amount' => $paymentAmount,
                'payment_method' => 'bank_transfer',
                'reference_number' => 'EFT-TX-' . rand(100000, 999999),
                'payment_date' => Carbon::now()->subDays(1),
                'notes' => '50% advance bank transfer against invoice',
            ], $admin);
        }

        // 4. Create an independent direct delivery GRN from Supplier 2
        $independentItems = [];
        foreach ($products->skip(2)->take(2) as $prod) {
            $independentItems[] = [
                'product_id' => $prod->id,
                'batch_number' => 'DIR-REC-' . rand(1000, 9999),
                'manufacturing_date' => Carbon::now()->subMonths(1)->toDateString(),
                'expiry_date' => Carbon::now()->addMonths(18)->toDateString(),
                'quantity_received' => 50,
                'unit_purchase_price' => $prod->purchase_price ?: 40.00,
                'unit_selling_price' => ($prod->purchase_price ?: 40.00) * 1.4,
            ];
        }

        if (!empty($independentItems)) {
            $procurementService->receiveStock([
                'supplier_id' => $supplier2->id,
                'branch_id' => $branch->id,
                'delivery_note_number' => 'DN-SP-' . rand(10000, 99999),
                'supplier_invoice_number' => 'SP-INV-' . rand(20000, 99999),
                'received_date' => Carbon::now()->subDay()->toDateString(),
                'notes' => 'Direct urgent consignment.',
                'items' => $independentItems,
            ], $admin);
        }
    }
}
