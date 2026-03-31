<?php
declare(strict_types=1);

namespace app\model\system;

use core\base\Model;
use think\model\relation\HasMany;

class Dictionary extends Model
{
    protected $table = 'dictionaries';

    protected $fillable = [
        'name', 'code', 'description', 'status', 'sort',
    ];

    protected $type = [
        'status' => 'integer',
        'sort'   => 'integer',
    ];

    protected $append = ['status_text'];

    /**
     * 关联字典项
     */
    public function items(): HasMany
    {
        return $this->hasMany(DictionaryItem::class, 'dictionary_id')
            ->order('sort asc, id asc');
    }

    /**
     * 状态文本
     */
    public function getStatusTextAttr($value, $data): string
    {
        if (!isset($data['status'])) return '';
        return $this->getStatusText($data['status'], [1 => '正常', 0 => '禁用']);
    }
}
