<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\ExpenseVoucher;
use App\Models\GoodsReceivedNote;
use App\Models\JournalEntry;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\Unit;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\FinancialReportService;
use App\Services\PosService;
use App\Services\ProcurementService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\PharmacySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Pharmacy $pharmacy;
    protected Branch $branch;
    protected ProductCategory $category;
    protected Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolesAndPermissionsSeeder::class,
            PharmacySeeder::class,
            UserSeeder::class,
            SystemSettingSeeder::class,
            AccountingSeeder::class,
        ]);

        $this->user = User::where('email', 'owner@afyameds.co.ke')->firstOrFail();
        $this->pharmacy = Pharmacy::firstOrFail();
        $this->branch = Branch::firstOrFail();
        $this->category = ProductCategory::create([
            'pharmacy_id' => $this->pharmacy->id,
            'name' => 'Antibiotics & General',
            'code' => 'ABX',
        ]);
        $this->unit = Unit::create([
            'pharmacy_id' => $this->pharmacy->id,
            'name' => 'Tablet',
            'short_name' => 'tab',
            'operator' => '*',
            'operation_value' => 1,
        ]);
    }

    public function test_chart_of_accounts_can_be_retrieved_and_new_account_added(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/accounting/chart-of-accounts');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [['id', 'code', 'name', 'type', 'current_balance']]]);

        // Add custom expense account
        $addResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/accounting/chart-of-accounts', [
                'code' => '6980',
                'name' => 'Waste Disposal & Biohazard Fees',
                'type' => 'expense',
                'subtype' => 'operating_expense',
                'description' => 'Clinical waste management and incineration fees.',
            ]);

        $addResponse->assertCreated()
            ->assertJsonPath('data.code', '6980');

        $this->assertDatabaseHas('chart_of_accounts', [
            'pharmacy_id' => $this->pharmacy->id,
            'code' => '6980',
        ]);
    }

    public function test_manual_journal_entry_enforces_double_entry_balance(): void
    {
        $cashAcc = Account::where('pharmacy_id', $this->pharmacy->id)->where('code', '1000')->firstOrFail();
        $capitalAcc = Account::where('pharmacy_id', $this->pharmacy->id)->where('code', '3000')->firstOrFail();

        // 1. Unbalanced entry should fail
        $failResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/accounting/journal-entries', [
                'description' => 'Unbalanced capital injection',
                'lines' => [
                    ['account_id' => $cashAcc->id, 'debit' => 50000.00, 'credit' => 0.00],
                    ['account_id' => $capitalAcc->id, 'debit' => 0.00, 'credit' => 45000.00], // Difference of 5,000
                ],
            ]);

        $failResponse->assertStatus(422);

        // 2. Balanced entry should succeed
        $successResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/accounting/journal-entries', [
                'description' => 'Owner supplementary capital deposit',
                'lines' => [
                    ['account_id' => $cashAcc->id, 'debit' => 50000.00, 'credit' => 0.00],
                    ['account_id' => $capitalAcc->id, 'debit' => 0.00, 'credit' => 50000.00],
                ],
            ]);

        $successResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_debit', '50000.00')
            ->assertJsonPath('data.total_credit', '50000.00');

        $this->assertDatabaseHas('journal_entries', [
            'pharmacy_id' => $this->pharmacy->id,
            'description' => 'Owner supplementary capital deposit',
            'status' => 'posted',
        ]);
    }

    public function test_pos_sale_auto_posts_journal_entry_with_revenue_and_cogs(): void
    {
        $product = Product::create([
            'pharmacy_id' => $this->pharmacy->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Amoxil 500mg',
            'generic_name' => 'Amoxicillin Trihydrate',
            'sku' => 'TEST-AMX-01',
            'selling_price' => 300.00,
            'cost_price' => 150.00,
            'is_active' => true,
        ]);

        $batch = ProductBatch::create([
            'pharmacy_id' => $this->pharmacy->id,
            'branch_id' => $this->branch->id,
            'product_id' => $product->id,
            'batch_number' => 'BAT-AMX-001',
            'expiry_date' => now()->addMonths(12),
            'quantity_received' => 100,
            'quantity_remaining' => 100,
            'purchase_price_per_unit' => 150.00,
            'selling_price_per_unit' => 300.00,
            'status' => 'active',
        ]);

        $posService = app(PosService::class);
        $sale = $posService->createSale([
            'branch_id' => $this->branch->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_selling_price' => 300.00],
            ],
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 600.00, 'amount_tendered' => 600.00],
            ],
        ], $this->user);

        // Check that a journal entry was created for this sale
        $entry = JournalEntry::where('reference_type', 'sale')
            ->where('reference_id', $sale->id)
            ->with('lines.account')
            ->first();

        $this->assertNotNull($entry, 'Journal entry should be created automatically upon sale');
        $this->assertEquals(600.00 + 300.00, (float) $entry->total_debit, 'Total debit must match Cash Dr (600) + COGS Dr (300)');
        $this->assertEquals($entry->total_debit, $entry->total_credit, 'Debit must equal credit');

        // Check accounts credited and debited
        $revenueLine = $entry->lines->firstWhere('account.code', '4000');
        $this->assertNotNull($revenueLine);
        $this->assertEquals(600.00, (float) $revenueLine->credit);

        $cogsLine = $entry->lines->firstWhere('account.code', '5000');
        $this->assertNotNull($cogsLine);
        $this->assertEquals(300.00, (float) $cogsLine->debit); // 2 units * 150.00 cost

        $inventoryLine = $entry->lines->firstWhere('account.code', '1200');
        $this->assertNotNull($inventoryLine);
        $this->assertEquals(300.00, (float) $inventoryLine->credit);
    }

    public function test_grn_intake_auto_posts_journal_entry_to_inventory_and_accounts_payable(): void
    {
        $supplier = Supplier::create([
            'pharmacy_id' => $this->pharmacy->id,
            'name' => 'Metro Pharmaceuticals',
            'contact_person' => 'John',
            'phone' => '0711000111',
            'outstanding_balance' => 0.00,
        ]);

        $product = Product::create([
            'pharmacy_id' => $this->pharmacy->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Paracetamol 500mg',
            'generic_name' => 'Paracetamol',
            'sku' => 'TEST-PARA-01',
            'selling_price' => 50.00,
            'cost_price' => 20.00,
            'is_active' => true,
        ]);

        $procurementService = app(ProcurementService::class);
        $grn = $procurementService->receiveStock([
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'supplier_invoice_number' => 'INV-METRO-001',
            'received_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'batch_number' => 'BAT-PARA-001',
                    'expiry_date' => now()->addMonths(18)->toDateString(),
                    'quantity_received' => 200,
                    'unit_purchase_price' => 20.00,
                    'unit_selling_price' => 50.00,
                ],
            ],
        ], $this->user);

        // Check journal entry for GRN: Inventory Asset Dr (4,000), Accounts Payable Cr (4,000)
        $entry = JournalEntry::where('reference_type', 'grn')
            ->where('reference_id', $grn->id)
            ->with('lines.account')
            ->first();

        $this->assertNotNull($entry);
        $this->assertEquals(4000.00, (float) $entry->total_debit);
        $this->assertEquals(4000.00, (float) $entry->total_credit);

        $invLine = $entry->lines->firstWhere('account.code', '1200');
        $this->assertEquals(4000.00, (float) $invLine->debit);

        $apLine = $entry->lines->firstWhere('account.code', '2000');
        $this->assertEquals(4000.00, (float) $apLine->credit);
    }

    public function test_supplier_payment_auto_posts_journal_entry_clearing_accounts_payable(): void
    {
        $supplier = Supplier::create([
            'pharmacy_id' => $this->pharmacy->id,
            'name' => 'Crown Healthcare',
            'phone' => '0722000222',
            'outstanding_balance' => 15000.00,
        ]);

        $procurementService = app(ProcurementService::class);
        $payment = $procurementService->recordSupplierPayment([
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'amount' => 15000.00,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'EFT-883920',
            'payment_date' => now()->toDateString(),
        ], $this->user);

        // Check journal entry: Accounts Payable Dr (15,000), Bank Cr (15,000)
        $entry = JournalEntry::where('reference_type', 'supplier_payment')
            ->where('reference_id', $payment->id)
            ->with('lines.account')
            ->first();

        $this->assertNotNull($entry);
        $this->assertEquals(15000.00, (float) $entry->total_debit);

        $apLine = $entry->lines->firstWhere('account.code', '2000');
        $this->assertEquals(15000.00, (float) $apLine->debit);

        $bankLine = $entry->lines->firstWhere('account.code', '1010');
        $this->assertEquals(15000.00, (float) $bankLine->credit);
    }

    public function test_expense_voucher_creation_and_approval_posts_operating_expense_journal(): void
    {
        $category = ExpenseCategory::where('pharmacy_id', $this->pharmacy->id)->firstOrFail();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/expenses/vouchers', [
                'expense_category_id' => $category->id,
                'paid_to' => 'Kenya Power & Lighting',
                'amount' => 6500.00,
                'payment_method' => 'mpesa',
                'reference_number' => 'QKL093847',
                'description' => 'Pharmacy electricity bill payment for the month',
                'auto_approve' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved');

        $voucherId = $response->json('data.id');

        // Check journal entry posted
        $entry = JournalEntry::where('reference_type', 'expense_voucher')
            ->where('reference_id', $voucherId)
            ->with('lines.account')
            ->first();

        $this->assertNotNull($entry);
        $this->assertEquals(6500.00, (float) $entry->total_debit);
        $this->assertEquals(6500.00, (float) $entry->total_credit);

        $bankLine = $entry->lines->firstWhere('account.code', '1010');
        $this->assertEquals(6500.00, (float) $bankLine->credit);
    }

    public function test_profit_and_loss_statement_calculates_gross_profit_and_net_profit_accurately(): void
    {
        $reportService = app(FinancialReportService::class);
        $pnl = $reportService->getProfitAndLoss($this->pharmacy);

        $this->assertArrayHasKey('revenue', $pnl);
        $this->assertArrayHasKey('cost_of_sales', $pnl);
        $this->assertArrayHasKey('gross_profit', $pnl);
        $this->assertArrayHasKey('operating_expenses', $pnl);
        $this->assertArrayHasKey('net_operating_profit', $pnl);

        $revenue = $pnl['revenue']['total_revenue'];
        $cogs = $pnl['cost_of_sales']['total_cogs'];
        $expectedGross = round($revenue - $cogs, 2);
        $this->assertEquals($expectedGross, $pnl['gross_profit']);

        $expenses = $pnl['operating_expenses']['total_expenses'];
        $expectedNet = round($expectedGross - $expenses, 2);
        $this->assertEquals($expectedNet, $pnl['net_operating_profit']);
    }

    public function test_balance_sheet_satisfies_accounting_equation_assets_equal_liabilities_plus_equity(): void
    {
        $reportService = app(FinancialReportService::class);
        $sheet = $reportService->getBalanceSheet($this->pharmacy);

        $this->assertTrue($sheet['is_balanced'], 'Balance Sheet must satisfy Assets = Liabilities + Equity');
        $this->assertEquals(0.00, (float) $sheet['check_difference']);
    }

    public function test_trial_balance_verifies_grand_total_debit_equals_credit(): void
    {
        $reportService = app(FinancialReportService::class);
        $trial = $reportService->getTrialBalance($this->pharmacy);

        $this->assertTrue($trial['is_balanced'], 'Trial balance total debits must equal total credits');
        $this->assertEquals((float) $trial['total_debit'], (float) $trial['total_credit']);
    }
}
