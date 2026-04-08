<?php
declare(strict_types=1);

namespace app\model\system;

use core\base\Model;
use think\model\relation\BelongsTo;

class DictionaryItem extends Model
{
    protected $name = 'dictionary_items';

    protected $fillable = [
        'dictionary_id', 'label', 'value', 'tag_type',
        'description', 'status', 'sort',
    ];

    protected $type = [
        'dictionary_id' => 'integer',
        'status'        => 'integer',
        'sort'          => 'integer',
    ];

    protected $append = ['status_text'];

    /**
     * 关联字典
     */
    public function dictionary(): BelongsTo
    {
        return $this->belongsTo(Dictionary::class, 'dictionary_id');
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
