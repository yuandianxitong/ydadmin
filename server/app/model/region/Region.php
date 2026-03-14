<?php
declare(strict_types=1);

namespace app\model\region;

use core\base\Model;
use think\model\relation\HasMany;

class Region extends Model
{
    protected $table = 'regions';

    protected $autoWriteTimestamp = false;
    protected $deleteTime = false;
    protected $hidden = [];

    protected $fillable = [
        'parent_id', 'name', 'code', 'level', 'sort', 'status',
    ];

    // 层级常量
    const LEVEL_PROVINCE = 1;
    const LEVEL_CITY     = 2;
    const LEVEL_DISTRICT = 3;

    protected $type = [
        'parent_id' => 'integer',
        'level'     => 'integer',
        'sort'      => 'integer',
        'status'    => 'integer',
    ];

    /**
     * 子级关联
     */
    public function children(): HasMany
    {
        return $this->hasMany(Region::class, 'parent_id', 'id');
    }

    /**
     * 层级文本获取器
     */
    public function getLevelTextAttr($value, $data): string
    {
        $levelMap = [
            self::LEVEL_PROVINCE => '省',
            self::LEVEL_CITY     => '市',
            self::LEVEL_DISTRICT => '区',
        ];
        return $levelMap[(int) ($data['level'] ?? 0)] ?? '未知';
    }
}
