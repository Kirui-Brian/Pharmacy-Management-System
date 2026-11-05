<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\ExpenseCategory;
use App\Models\Pharmacy;
use Illuminate\Database\Seeder;

class AccountingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pharmacies = Pharmacy::all();

        foreach ($pharmacies as $pharmacy) {
            $accounts = [
                // 1000s: Current Assets
                [
                    'code' => '1000',
                    'name' => 'Cash in Till (Cashier Drawer)',
                    'type' => 'asset',
                    'subtype' => 'current_asset',
                    'opening_balance' => 20000.00,
                    'is_system' => true,
                    'description' => 'Physical cash held in retail cashier tills for counter change and sales.',
                ],
                [
                    'code' => '1010',
                    'name' => 'Bank / M-Pesa Business Account',
                    'type' => 'asset',
                    'subtype' => 'current_asset',
                    'opening_balance' => 150000.00,
                    'is_system' => true,
                    'description' => 'Operating bank account and M-Pesa Buy Goods/Paybill till collections.',
                ],
                [
                    'code' => '1020',
                    'name' => 'Petty Cash Fund',
                    'type' => 'asset',
                    'subtype' => 'current_asset',
                    'opening_balance' => 10000.00,
                    'is_system' => true,
                    'description' => 'Emergency small cash fund held by the branch manager for minor expenses.',
                ],
                [
                    'code' => '1200',
                    'name' => 'Merchandise Inventory Asset',
                    'type' => 'asset',
                    'subtype' => 'inventory',
                    'opening_balance' => 0.00,
                    'is_system' => true,
                    'description' => 'Historic purchase value of active stock batches physically in store.',
                ],
                [
                    'code' => '1300',
                    'name' => 'Accounts Receivable (Customer Debt)',
                    'type' => 'asset',
                    'subtype' => 'accounts_receivable',
                    'opening_balance' => 0.00,
                    'is_system' => true,
                    'description' => 'Outstanding balances due from credit customers and institutional accounts.',
                ],

                // 2000s: Liabilities
                [
                    'code' => '2000',
                    'name' => 'Accounts Payable (Supplier Debt)',
                    'type' => 'liability',
                    'subtype' => 'accounts_payable',
                    'opening_balance' => 0.00,
                    'is_system' => true,
                    'description' => 'Amounts owed to pharmaceutical distributors and suppliers for GRN intakes.',
                ],
                [
                    'code' => '2050',
                    'name' => 'Value Added Tax (VAT) Payable',
                    'type' => 'liability',
                    'subtype' => 'tax_payable',
                    'opening_balance' => 0.00,
                    'is_system' => true,
                    'description' => 'Sales tax collected on applicable OTC items awaiting remittance to KRA.',
                ],

                // 3000s: Equity
                [
                    'code' => '3000',
                    'name' => "Owner's Capital",
                    'type' => 'equity',
                    'subtype' => 'capital',
                    'opening_balance' => 180000.00,
                    'is_system' => true,
                    'description' => 'Initial capital contributed to establish the pharmacy practice.',
                ],
                [
                    'code' => '3100',
                    'name' => 'Retained Earnings',
                    'type' => 'equity',
                    'subtype' => 'retained_earnings',
                    'opening_balance' => 0.00,
                    'is_system' => true,
                    'description' => 'Cumulative net profit retained in the pharmacy business across periods.',
                ],

                // 4000s: Revenue
                [
                    'code' => '4000',
                    'name' => 'Pharmaceutical Sales Revenue',
                    'type' => 'revenue',
                    'subtype' => 'sales',
                    'opening_balance' => 0.00,
                    'is_system' => true,
                    'description' => 'Gross income earned from retail dispensing and OTC medicine counter sales.',
                ],
                [
                    'code' => '4100',
                    'name' => 'Clinical Consultation & Screening Fees',
                    'type' => 'revenue',
                    'subtype' => 'services',
                    'opening_balance' => 0.00,
                    'is_system' => false,
                    'description' => 'Income from blood pressure, blood glucose tests, and clinical reviews.',
                ],

                // 5000s: Cost of Goods Sold
                [
                    'code' => '5000',
                    'name' => 'Cost of Goods Sold (COGS)',
                    'type' => 'expense',
                    'subtype' => 'cogs',
                    'opening_balance' => 0.00,
                    'is_system' => true,
                    'description' => 'Exact historic purchase cost of stock batches depleted during dispensing.',
                ],
                [
                    'code' => '5100',
                    'name' => 'Inventory Shrinkage & Expiry Loss',
                    'type' => 'expense',
                    'subtype' => 'cogs',
                    'opening_balance' => 0.00,
                    'is_system' => true,
                    'description' => 'Value of medicines written off due to expiry, breakage, or audits.',
                ],

                // 6000s: Operating Expenses
                [
                    'code' => '6100',
                    'name' => 'Rent & Facility Lease',
                    'type' => 'expense',
                    'subtype' => 'operating_expense',
                    'opening_balance' => 0.00,
                    'is_system' => false,
                    'description' => 'Monthly premises rent and plaza service charges.',
                ],
                [
                    'code' => '6200',
                    'name' => 'Electricity, Water & Utilities',
                    'type' => 'expense',
                    'subtype' => 'operating_expense',
                    'opening_balance' => 0.00,
                    'is_system' => false,
                    'description' => 'KPLC electricity bills, cold chain backup power, and water utility.',
                ],
                [
                    'code' => '6300',
                    'name' => 'Staff Salaries, Wages & Allowances',
                    'type' => 'expense',
                    'subtype' => 'operating_expense',
                    'opening_balance' => 0.00,
                    'is_system' => false,
                    'description' => 'Pharmacist salaries, locum wages, and cashier allowances.',
                ],
                [
                    'code' => '6400',
                    'name' => 'Pharmacy Packaging & Consumables',
                    'type' => 'expense',
                    'subtype' => 'operating_expense',
                    'opening_balance' => 0.00,
                    'is_system' => false,
                    'description' => 'Dispensing envelopes, amber medicine bottles, thermal receipt paper rolls.',
                ],
                [
                    'code' => '6500',
                    'name' => 'Transport, Fuel & Delivery',
                    'type' => 'expense',
                    'subtype' => 'operating_expense',
                    'opening_balance' => 0.00,
                    'is_system' => false,
                    'description' => 'Rider deliveries for home patients and stock collection transport.',
                ],
                [
                    'code' => '6600',
                    'name' => 'Equipment Maintenance & Software',
                    'type' => 'expense',
                    'subtype' => 'operating_expense',
                    'opening_balance' => 0.00,
                    'is_system' => false,
                    'description' => 'Fridge servicing, air conditioning maintenance, computer support.',
                ],
                [
                    'code' => '6700',
                    'name' => 'Licenses, Permits & Regulatory Fees',
                    'type' => 'expense',
                    'subtype' => 'operating_expense',
                    'opening_balance' => 0.00,
                    'is_system' => false,
                    'description' => 'Pharmacy & Poisons Board (PPB) annual license, county council permits.',
                ],
                [
                    'code' => '6900',
                    'name' => 'Bank Charges & Payment Processing Fees',
                    'type' => 'expense',
                    'subtype' => 'operating_expense',
                    'opening_balance' => 0.00,
                    'is_system' => false,
                    'description' => 'M-Pesa business tariff deductions, bank maintenance, POS merchant fees.',
                ],
                [
                    'code' => '6950',
                    'name' => 'General & Administrative Sundry',
                    'type' => 'expense',
                    'subtype' => 'operating_expense',
                    'opening_balance' => 0.00,
                    'is_system' => false,
                    'description' => 'Cleaning detergents, staff tea, and office stationery.',
                ],
            ];

            $createdAccounts = [];
            foreach ($accounts as $acc) {
                $createdAccounts[$acc['code']] = Account::updateOrCreate(
                    [
                        'pharmacy_id' => $pharmacy->id,
                        'code' => $acc['code'],
                    ],
                    array_merge($acc, [
                        'current_balance' => $acc['opening_balance'],
                    ])
                );
            }

            // Seed default expense categories mapped to the 6000s accounts
            $categories = [
                ['name' => 'Premises Rent & Service Charge', 'code' => '6100', 'desc' => 'Pharmacy store monthly commercial rent.'],
                ['name' => 'Electricity & Cold Chain Backup', 'code' => '6200', 'desc' => 'Power, vaccine fridge backup generator fuel, water.'],
                ['name' => 'Staff Salaries & Locum Wages', 'code' => '6300', 'desc' => 'Pharmacists, technicians, cashiers compensation.'],
                ['name' => 'Dispensing Envelopes & Packaging', 'code' => '6400', 'desc' => 'Medicine envelopes, dispensing bottles, thermal paper.'],
                ['name' => 'Patient Delivery & Transportation', 'code' => '6500', 'desc' => 'Boda-boda courier and delivery expenses.'],
                ['name' => 'IT Software & Hardware Repairs', 'code' => '6600', 'desc' => 'System maintenance, barcode scanner replacement.'],
                ['name' => 'PPB Annual Licenses & Council Permits', 'code' => '6700', 'desc' => 'Pharmacy Poisons Board and County Government single business permits.'],
                ['name' => 'M-Pesa & Bank Transaction Fees', 'code' => '6900', 'desc' => 'Payment gateway and mobile money charges.'],
                ['name' => 'General Office & Cleaning Sundry', 'code' => '6950', 'desc' => 'Store hygiene, sanitizers, stationery, and tea.'],
            ];

            foreach ($categories as $cat) {
                ExpenseCategory::updateOrCreate(
                    [
                        'pharmacy_id' => $pharmacy->id,
                        'name' => $cat['name'],
                    ],
                    [
                        'account_id' => $createdAccounts[$cat['code']]->id ?? null,
                        'description' => $cat['desc'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
