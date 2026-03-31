<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\model\article;

use core\base\Model;

class Article extends Model
{
    protected $table = 'articles';

    protected $fillable = [
        'category_id', 'title', 'cover', 'summary', 'content',
        'tags', 'author', 'view_count', 'status', 'publish_at', 'admin_id',
    ];

    // 状态常量
    const STATUS_DRAFT     = 0;
    const STATUS_PUBLISHED = 1;

    protected $type = [
        'category_id' => 'integer',
        'status'      => 'integer',
        'admin_id'    => 'integer',
        'view_count'  => 'integer',
        'tags'        => 'json',
    ];

    protected $append = ['status_text'];

    /**
     * 所属分类
     */
    public function category(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'category_id', 'id');
    }

    /**
     * 状态文本获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        $statusMap = [
            self::STATUS_DRAFT     => '草稿',
            self::STATUS_PUBLISHED => '已发布',
        ];
        return $this->getStatusText((int) ($data['status'] ?? 0), $statusMap);
    }
}
