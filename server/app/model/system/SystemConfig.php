<?php

declare(strict_types=1);

namespace app\model\system;

use core\base\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 系统配置（system_configs 表）。
 * - `convertValueByType()` 按 `config_type` 把字符串 `config_value` 转成业务类型。
 * - `config_options`/`config_depends` 是 JSON 列，cast 为 array。
 * - `is_public` 标记该行是否出现在 config/global。
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
        'is_public'      => 'integer',
        'config_options' => 'array',
        'config_depends' => 'array',
    ];

    /**
     * 按 `config_type` 转换 `config_value` 的业务值：
     *   - boolean → 仅 '1'/'true'/'yes'/'on' 为真（忽略大小写与首尾空白）；其余为假，含 'false'、'0'、''
     *   - number  → 含小数点转 float，否则转 int；非数字原样返回字符串
     *   - json    → 解码；空串或非法 JSON 回退 []；合法的假值（false、0、""、null）保留原值
     *   - default → 原样返回字符串
     */
    public static function convertValueByType(string $value, string $type): mixed
    {
        switch ($type) {
            case 'boolean':
                return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
            case 'number':
                return is_numeric($value) ? (str_contains($value, '.') ? (float) $value : (int) $value) : $value;
            case 'json':
                if (trim($value) === '') {
                    return [];
                }
                $decoded = json_decode($value, true);

                return json_last_error() === JSON_ERROR_NONE ? $decoded : [];
            default:
                return $value;
        }
    }
}
