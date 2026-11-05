<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'pharmacy_id',
        'branch_id',
        'key',
        'value',
        'group',
        'type',
        'description',
    ];

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public static function get(string $key, mixed $default = null, ?int $branchId = null): mixed
    {
        $query = static::where('key', $key);
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $setting = $query->first();
        if (!$setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            'float' => (float) $setting->value,
            'json' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }

    public static function set(string $key, mixed $value, string $group = 'general', ?int $branchId = null, ?int $pharmacyId = null): static
    {
        $type = match (gettype($value)) {
            'boolean' => 'boolean',
            'integer' => 'integer',
            'double' => 'float',
            'array', 'object' => 'json',
            default => 'string',
        };

        $storedValue = ($type === 'json') ? json_encode($value) : (string) $value;

        return static::updateOrCreate(
            [
                'key' => $key,
                'branch_id' => $branchId,
                'pharmacy_id' => $pharmacyId,
            ],
            [
                'value' => $storedValue,
                'group' => $group,
                'type' => $type,
            ]
        );
    }
}
