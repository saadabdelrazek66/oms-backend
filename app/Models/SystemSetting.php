<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
        'name_ar',
        'description_ar',
        'options',
        'sort_order',
    ];

    protected $casts = [
        'options' => 'array',
        'sort_order' => 'integer',
    ];

    /**
     * Get casted value attribute
     */
    public function getCastedValueAttribute()
    {
        return self::castValue($this->value, $this->type);
    }

    /**
     * Cast raw value to its PHP type
     */
    public static function castValue($value, string $type)
    {
        if ($value === null) return null;

        switch ($type) {
            case 'boolean':
            case 'bool':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'number':
            case 'int':
            case 'integer':
                return is_numeric($value) ? (str_contains((string)$value, '.') ? (float)$value : (int)$value) : 0;
            case 'float':
                return (float)$value;
            case 'array':
            case 'json':
                if (is_array($value)) return $value;
                $decoded = json_decode((string)$value, true);
                return json_last_error() === JSON_ERROR_NONE ? $decoded : [];
            default:
                return (string)$value;
        }
    }

    /**
     * Format value before saving
     */
    public static function serializeValue($value, string $type)
    {
        if ($value === null) return null;

        switch ($type) {
            case 'boolean':
            case 'bool':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            case 'array':
            case 'json':
                return is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
            default:
                return (string)$value;
        }
    }
}
