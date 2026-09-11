<?php

declare(strict_types=1);

namespace app\model\system;

use core\base\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 系统配置（system_configs 表）。`convertValueByType()` 按 `config_type` 把
 * `config_value` 这个字符串值转成业务类型；`config_options`/`config_depends`
 * 是 JSON 列，cast 为 array。
 */
class SystemConfig extends Model
{
    use SoftDeletes;

    protected $table = 'system_configs';

    /** @var array<int, string> */
    protected $guarded = ['id'];

    /** @var array<string, string> */
    protected $casts = [
        'sort_order'     => 'integer',
        'status'         => 'integer',
        'config_options' => 'array',
        'config_depends' => 'array',
    ];

    /**
     * 按 `config_type` 转换 `config_value` 的业务值：
     *   - boolean → (bool)$value
     *   - number  → 含小数点转 float，否则转 int；非数字原样返回字符串
     *   - json    → json_decode($value, true)，解析失败/为空回退 []
     *   - default → 原样返回字符串
     */
    public static function convertValueByType(string $value, string $type): mixed
    {
        switch ($type) {
            case 'boolean':
                return (bool) $value;
            case 'number':
                return is_numeric($value) ? (str_contains($value, '.') ? (float) $value : (int) $value) : $value;
            case 'json':
                return json_decode($value, true) ?: [];
            default:
                return $value;
        }
    }
}
