<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    protected array $groupMap = [
        'company_name' => 'general',
        'tax_pin' => 'general',
        'currency_code' => 'general',
        'currency_symbol' => 'general',
        'low_stock_threshold' => 'inventory',
        'expiry_alert_days_first' => 'inventory',
        'expiry_alert_days_critical' => 'inventory',
        'block_expired_sales' => 'inventory',
        'receipt_header' => 'pos',
        'receipt_footer' => 'pos',
        'default_prescription_tax' => 'pos',
        'default_general_tax' => 'pos',
        'enable_split_payments' => 'pos',
        'allow_credit_sales' => 'pos',
    ];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $request->query('branch_id', $user?->branch_id);

        $settings = SystemSetting::where(function ($query) use ($user, $branchId) {
            $query->whereNull('pharmacy_id')
                ->orWhere('pharmacy_id', $user?->pharmacy_id);
        })
        ->where(function ($query) use ($branchId) {
            $query->whereNull('branch_id')
                ->orWhere('branch_id', $branchId);
        })
        ->get();

        $grouped = $settings->groupBy('group')->map(function ($items) {
            return $items->keyBy('key')->map(function ($item) {
                return [
                    'key' => $item->key,
                    'value' => match ($item->type) {
                        'boolean' => filter_var($item->value, FILTER_VALIDATE_BOOLEAN),
                        'integer' => (int) $item->value,
                        'float' => (float) $item->value,
                        'json' => json_decode($item->value, true),
                        default => $item->value,
                    },
                    'type' => $item->type,
                    'description' => $item->description,
                ];
            });
        });

        // Also supply flat values for direct frontend binding convenience
        $flat = [];
        foreach ($settings as $setting) {
            $flat[$setting->key] = match ($setting->type) {
                'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
                'integer' => (int) $setting->value,
                'float' => (float) $setting->value,
                'json' => json_decode($setting->value, true),
                default => $setting->value,
            };
        }

        return response()->json([
            'status' => 'success',
            'data' => $grouped,
            'flat' => $flat,
        ]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $user = $request->user();
        $rawSettings = $request->input('settings', []);
        $normalized = [];

        // Case 1: Sequential list of objects [{ key: '...', value: '...' }]
        if (is_array($rawSettings) && isset($rawSettings[0]) && is_array($rawSettings[0]) && isset($rawSettings[0]['key'])) {
            foreach ($rawSettings as $item) {
                $k = $item['key'];
                $normalized[$k] = [
                    'value' => $item['value'] ?? null,
                    'group' => $item['group'] ?? ($this->groupMap[$k] ?? 'general'),
                ];
            }
        } else {
            // Case 2: Associative map (can include nested groups or flat keys)
            foreach ($rawSettings as $key => $val) {
                // If it's a known top-level group like 'general', 'inventory', 'pos'
                if (in_array($key, ['general', 'inventory', 'pos', 'notifications']) && is_array($val)) {
                    foreach ($val as $subKey => $subVal) {
                        $resolvedValue = is_array($subVal) && array_key_exists('value', $subVal) ? $subVal['value'] : $subVal;
                        $normalized[$subKey] = [
                            'value' => $resolvedValue,
                            'group' => $key,
                        ];
                    }
                } else {
                    // Flat key => value
                    $normalized[$key] = [
                        'value' => is_array($val) && array_key_exists('value', $val) ? $val['value'] : $val,
                        'group' => $this->groupMap[$key] ?? 'general',
                    ];
                }
            }
        }

        $updatedKeys = [];
        foreach ($normalized as $key => $data) {
            // Ignore non-scalar or meta keys that aren't settings
            if (in_array($key, ['general', 'inventory', 'pos', 'maintenance', 'notifications'])) {
                continue;
            }

            SystemSetting::set(
                key: $key,
                value: $data['value'],
                group: $data['group'],
                branchId: null,
                pharmacyId: $user->pharmacy_id
            );

            $updatedKeys[] = $key;
        }

        try {
            AuditLog::log('settings_updated', 'System settings updated: ' . implode(', ', $updatedKeys));
        } catch (\Throwable) {}

        return response()->json([
            'status' => 'success',
            'message' => 'Settings updated successfully',
            'updated_keys' => $updatedKeys,
        ]);
    }
}
