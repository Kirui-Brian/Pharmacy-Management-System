<?php

namespace App\Http\Resources;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $accessibleBranches = [];
        if ($this->hasAnyRole(['owner', 'super-admin'])) {
            $accessibleBranches = Branch::where('pharmacy_id', $this->pharmacy_id)
                ->where('is_active', true)
                ->get(['id', 'name', 'code', 'is_main']);
        } elseif ($this->branch) {
            $accessibleBranches = [$this->branch->only(['id', 'name', 'code', 'is_main'])];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'license_number' => $this->license_number,
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at?->toISOString(),
            'pharmacy' => $this->pharmacy ? [
                'id' => $this->pharmacy->id,
                'name' => $this->pharmacy->name,
                'tax_pin' => $this->pharmacy->tax_pin,
                'currency_code' => $this->pharmacy->currency_code,
                'currency_symbol' => $this->pharmacy->currency_symbol,
            ] : null,
            'branch' => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
                'code' => $this->branch->code,
                'is_main' => $this->branch->is_main,
            ] : null,
            'accessible_branches' => $accessibleBranches,
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
        ];
    }
}
