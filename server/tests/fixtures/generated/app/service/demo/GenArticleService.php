<?php

declare(strict_types=1);

namespace app\service\demo;

use app\repository\demo\GenArticleRepository;
use core\base\Service;
use core\exception\BusinessException;
use DI\Attribute\Inject;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * 生成器夹具表（由代码生成器生成）。
 *
 * 只调 Repository：查询条件与数据权限都在 GenArticleRepository 里，这一层不直接调用数据库门面。
 * 写操作一律包 runInTransaction()；缓存失效之类的副作用请在事务里用 afterCommit() 追加。
 *
 * 唯一性（slug）：写入前调 GenArticleRepository 的 existsBy* 预检查（经 DataScope::bypass() 看全表、含软删行），
 * 命中就抛业务提示；更新时把自己这一行用 $excludeId 排除掉。并发写入撞上唯一索引时捕获
 * UniqueConstraintViolationException 转成同一条业务错误，不返回 500。
 */
class GenArticleService extends Service
{
    #[Inject]
    protected GenArticleRepository $genArticleRepository;

    /**
     * 列表：keyword、区间等查询条件在 Repository 里按列类型展开。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getGenArticleList(array $params, int $page, int $limit): array
    {
        return $this->genArticleRepository->getGenArticleList($params, $page, $limit);
    }

    /** @return array<string, mixed> */
    public function getGenArticleDetail(int $id): array
    {
        return $this->findGenArticleOrFail($id);
    }

    /**
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     * @return array<string, mixed>
     */
    public function createGenArticle(array $data): array
    {
        $row = array_intersect_key($data, array_flip(['title', 'summary', 'content', 'cover_image', 'category', 'price', 'view_count', 'slug', 'published_at', 'status', 'sort']));
        if (isset($row['slug']) && $this->genArticleRepository->existsBySlug((string) $row['slug'])) {
            throw new BusinessException(lang('demo/gen_article.slug_exists'));
        }

        try {
            return $this->runInTransaction(fn (): array => $this->genArticleRepository->create($row));
        } catch (UniqueConstraintViolationException) {
            throw new BusinessException(lang('demo/gen_article.slug_exists'));
        }
    }

    /**
     * 局部更新：只写传了的字段，值为 null 的丢弃；白名单与 create 一致。
     *
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     */
    public function updateGenArticle(int $id, array $data): void
    {
        $this->findGenArticleOrFail($id);
        $update = array_filter(
            array_intersect_key($data, array_flip(['title', 'summary', 'content', 'cover_image', 'category', 'price', 'view_count', 'slug', 'published_at', 'status', 'sort'])),
            static fn ($value) => $value !== null
        );
        if ($update === []) {
            return;
        }
        // 自己这一行由 $excludeId 排除，不必先比较值有没有改动：那次字符串比较依赖 find() 的返回值，
        // 而 find() 受数据权限约束，取不到该列时 ?? '' 会把「没改」误判成「改了」，白跑一次查重。
        if (isset($update['slug']) && $this->genArticleRepository->existsBySlug((string) $update['slug'], $id)) {
            throw new BusinessException(lang('demo/gen_article.slug_exists'));
        }

        try {
            $this->runInTransaction(function () use ($id, $update): void {
                $this->genArticleRepository->update($id, $update);
            });
        } catch (UniqueConstraintViolationException) {
            throw new BusinessException(lang('demo/gen_article.slug_exists'));
        }
    }

    public function deleteGenArticle(int $id): void
    {
        $this->findGenArticleOrFail($id);

        $this->runInTransaction(function () use ($id): void {
            $this->genArticleRepository->delete($id);
        });
    }

    /**
     * 批量删除：同一事务内逐条删除，任一 id 不存在则整体回滚（与 M1 的角色、字典批量删除同语义）。
     * 重复 id 先去重。
     *
     * @param list<int> $ids
     */
    public function batchDelete(array $ids): void
    {
        $this->runInTransaction(function () use ($ids): void {
            foreach (array_values(array_unique($ids)) as $id) {
                $this->deleteGenArticle($id);
            }
        });
    }

    public function updateStatus(int $id, int $status): void
    {
        $this->findGenArticleOrFail($id);

        $this->runInTransaction(function () use ($id, $status): void {
            $this->genArticleRepository->update($id, ['status' => $status]);
        });
    }

    /** @return array<string, mixed> */
    private function findGenArticleOrFail(int $id): array
    {
        return $this->genArticleRepository->find($id) ?? throw new BusinessException(lang('demo/gen_article.not_found'));
    }
}
