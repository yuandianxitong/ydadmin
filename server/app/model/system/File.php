<?php
declare(strict_types=1);

namespace app\model\system;

use core\base\Model;

class File extends Model
{
    protected $table = 'files';

    protected $fillable = [
        'name', 'path', 'url', 'mime_type', 'extension', 'size',
        'group', 'upload_by', 'storage',
    ];

    protected $type = [
        'size'      => 'integer',
        'upload_by' => 'integer',
    ];

    /**
     * 文件大小可读格式
     */
    public function getSizeTextAttr($value, $data): string
    {
        $size = $data['size'] ?? 0;
        if ($size < 1024) return $size . ' B';
        if ($size < 1048576) return round($size / 1024, 2) . ' KB';
        if ($size < 1073741824) return round($size / 1048576, 2) . ' MB';
        return round($size / 1073741824, 2) . ' GB';
    }

    /**
     * 是否为图片
     */
    public function getIsImageAttr($value, $data): bool
    {
        return str_starts_with($data['mime_type'] ?? '', 'image/');
    }
}
