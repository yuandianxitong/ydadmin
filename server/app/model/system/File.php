<?php

declare(strict_types=1);

namespace app\model\system;

use core\base\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 文件（files 表，契约 §2.9.1）。有 deleted_at → SoftDeletes；不受数据权限约束（spec §5.5 未列入）。
 *
 * 🔴 软删只保住行，保不住文件：删除时物理对象是**硬删**的（契约 §2.9.1 规定的顺序，见
 * FileService::deleteFile()），deleted_at 之后即便被置空，那一行也只是指向一个已经不存在的对象。
 *
 * `group` 是 MySQL 保留字。Illuminate 的 MySqlGrammar::wrapValue() 会给编译出的所有列标识符
 * 自动加反引号，所以 QueryBuilder 一律传裸列名（'files.group'）；手工拼反引号反而会被二次
 * 转义成非法标识符。只有 selectRaw()/whereRaw()/orderByRaw() 这类原生片段才需要自己加，
 * 本模型与 FileRepository 的原生片段只有不含列名的 COUNT(*)。
 */
class File extends Model
{
    use SoftDeletes;

    protected $table = 'files';

    /** @var array<string, string> */
    protected $casts = [
        'size'      => 'integer',
        'upload_by' => 'integer',
    ];
}
