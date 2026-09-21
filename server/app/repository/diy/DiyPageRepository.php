<?php

declare(strict_types=1);

namespace app\repository\diy;

use app\model\diy\DiyPage;
use core\base\Model;
use core\base\Repository;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * 装修页面仓储（diy_pages 表）。
 *
 * 不受数据权限约束：装修页是全站一份，表里没有 created_by / dept_id。
 * 不声明 $dataScoped，沿用基类默认值。
 */
class DiyPageRepository extends Repository
{
    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new DiyPage();
    }

    /**
     * 按 page_key + platform 取未删行。
     *
     * @return array<string, mixed>|null
     */
    public function findByKey(string $key, string $platform = 'uniapp'): ?array
    {
        return $this->query()
            ->where($this->qualify('page_key'), $key)
            ->where($this->qualify('platform'), $platform)
            ->first()?->toArray();
    }

    /**
     * page_key + platform 是否已被占用（含软删行：uk_pagekey_platform 不区分软删）。
     */
    public function existsKey(string $key, string $platform = 'uniapp'): bool
    {
        return $this->query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where($this->qualify('page_key'), $key)
            ->where($this->qualify('platform'), $platform)
            ->exists();
    }

    /**
     * 把已规范化的数组写入已发布树与页面设置，不要 Db::raw 拷列。
     *
     * @param list<array<string, mixed>> $components
     * @param array<string, mixed> $pageSettings
     */
    public function publishByKey(string $key, array $components, array $pageSettings, string $platform = 'uniapp'): void
    {
        $this->query()
            ->where($this->qualify('page_key'), $key)
            ->where($this->qualify('platform'), $platform)
            ->update([
                'components_published' => $components,
                'page_settings'        => $pageSettings,
            ]);
    }
}
