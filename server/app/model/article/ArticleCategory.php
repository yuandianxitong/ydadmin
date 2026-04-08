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

class ArticleCategory extends Model
{
    protected $name = 'article_categories';

    // 不使用软删除，硬删除
    protected $deleteTime = false;

    protected $fillable = [
        'parent_id', 'name', 'icon', 'sort', 'status',
    ];

    protected $type = [
        'parent_id' => 'integer',
        'sort'      => 'integer',
        'status'    => 'integer',
    ];

    protected $append = ['status_text'];

    /**
     * 子分类
     */
    public function children(): \think\model\relation\HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id');
    }

    /**
     * 父分类
     */
    public function parent(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    /**
     * 分类下的文章
     */
    public function articles(): \think\model\relation\HasMany
    {
        return $this->hasMany(Article::class, 'category_id', 'id');
    }

    /**
     * 状态文本获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        return $this->getStatusText((int) ($data['status'] ?? 0), [1 => '启用', 0 => '禁用']);
    }
}
