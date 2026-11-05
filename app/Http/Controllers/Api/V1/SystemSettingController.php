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

        return response()->json([
            'status' => 'success',
            'data' => $grouped,
        ]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $user = $request->user();
        $updatedKeys = [];

        foreach ($request->settings as $settingData) {
            $key = $settingData['key'];
            $value = $settingData['value'];
            $group = $settingData['group'] ?? 'general';

            SystemSetting::set(
                key: $key,
                value: $value,
                group: $group,
                branchId: null, // Global pharmacy setting
                pharmacyId: $user->pharmacy_id
            );

            $updatedKeys[] = $key;
        }

        AuditLog::log('settings_updated', 'System settings updated: ' . implode(', ', $updatedKeys));

        return response()->json([
            'status' => 'success',
            'message' => 'Settings updated successfully',
        ]);
    }
}
