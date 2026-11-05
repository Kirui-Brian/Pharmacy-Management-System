<?php

namespace Database\Seeders;

use App\Models\Pharmacy;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $pharmacy = Pharmacy::first();
        $pharmacyId = $pharmacy?->id;

        $settings = [
            // General
            ['key' => 'currency_code', 'value' => 'KES', 'group' => 'general', 'type' => 'string', 'description' => 'Default system currency code'],
            ['key' => 'currency_symbol', 'value' => 'KES', 'group' => 'general', 'type' => 'string', 'description' => 'Default currency symbol'],
            ['key' => 'company_name', 'value' => 'Afya Meds Pharmacy Ltd', 'group' => 'general', 'type' => 'string', 'description' => 'Operating business legal name'],
            ['key' => 'tax_pin', 'value' => 'P051234567Z', 'group' => 'general', 'type' => 'string', 'description' => 'Tax Identification PIN (KRA)'],

            // Inventory & Alerts
            ['key' => 'low_stock_threshold', 'value' => '10', 'group' => 'inventory', 'type' => 'integer', 'description' => 'Global default product reorder level'],
            ['key' => 'expiry_alert_days_first', 'value' => '90', 'group' => 'inventory', 'type' => 'integer', 'description' => 'First stage expiry alert (days)'],
            ['key' => 'expiry_alert_days_critical', 'value' => '30', 'group' => 'inventory', 'type' => 'integer', 'description' => 'Critical stage expiry alert (days)'],
            ['key' => 'block_expired_sales', 'value' => 'true', 'group' => 'inventory', 'type' => 'boolean', 'description' => 'Strictly prevent checkout of expired batches'],

            // POS & Sales
            ['key' => 'receipt_header', 'value' => "AFYA MEDS PHARMACY LTD\nQuality Healthcare, Trusted Advice\nTel: +254 700 123 456", 'group' => 'pos', 'type' => 'string', 'description' => 'Receipt header text'],
            ['key' => 'receipt_footer', 'value' => "Thank you for choosing Afya Meds.\nGoods once sold are only returnable per Pharmacy policy.\nGet well soon!", 'group' => 'pos', 'type' => 'string', 'description' => 'Receipt footer text'],
            ['key' => 'default_prescription_tax', 'value' => '0', 'group' => 'pos', 'type' => 'float', 'description' => 'VAT percentage on human prescription medicines'],
            ['key' => 'default_general_tax', 'value' => '16', 'group' => 'pos', 'type' => 'float', 'description' => 'Standard VAT percentage on cosmetics/sundries'],
            ['key' => 'allow_credit_sales', 'value' => 'true', 'group' => 'pos', 'type' => 'boolean', 'description' => 'Enable customer credit sales with credit limits'],
            ['key' => 'enable_split_payments', 'value' => 'true', 'group' => 'pos', 'type' => 'boolean', 'description' => 'Allow split payments across Cash, M-Pesa, Card, AR'],
        ];

        foreach ($settings as $item) {
            SystemSetting::updateOrCreate(
                [
                    'pharmacy_id' => $pharmacyId,
                    'branch_id' => null,
                    'key' => $item['key'],
                ],
                $item
            );
        }
    }
}
