<?php

declare(strict_types=1);

namespace app\model\system;

use core\base\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 文件（files 表，契约 §2.9.1）。有 deleted_at → SoftDeletes；不受数据权限约束（spec §5.5 未列入）。
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
