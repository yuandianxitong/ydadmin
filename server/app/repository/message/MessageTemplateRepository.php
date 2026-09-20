<?php

declare(strict_types=1);

namespace app\repository\message;

use app\model\message\MessageTemplate;
use core\base\Model;
use core\base\Repository;
use core\support\Like;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * 消息模板仓储（M6b spec §2.1、§4.1）。不设 $dataScoped：模板是全局运营数据，表里也没有创建人与部门列（spec §2.5）。
 */
class MessageTemplateRepository extends Repository
{
    /** 转发常量：Service 禁止引用 app\model\* 的常量（check:context 规则三）。 */
    public const STATUS_ENABLED = MessageTemplate::STATUS_ENABLED;

    /** 内置模板：业务代码按编码触发，不可删除（计划设计决定 16）。 */
    public const BUILTIN_CODES = ['user_register', 'payment_success', 'feedback_received'];

    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new MessageTemplate();
    }

    /**
     * 启用且未软删的模板（SoftDeletes 全局作用域排除软删行）。
     *
     * @return array<string, mixed>|null
     */
    public function findActiveByCode(string $code): ?array
    {
        return $this->query()
            ->where($this->qualify('code'), $code)
            ->where($this->qualify('status'), self::STATUS_ENABLED)
            ->first()?->toArray();
    }

    /**
     * 编码是否已被占用，含软删行：uk_code 不区分软删，只查未删行会让「新建同编码」撞唯一键变 500（spec §4.1）。
     * withoutGlobalScope(SoftDeletingScope::class) 是规则五唯一放行的写法；phpstan 不认 withTrashed()。
     */
    public function codeExists(string $code): bool
    {
        return $this->query()->withoutGlobalScope(SoftDeletingScope::class)->where($this->qualify('code'), $code)->exists();
    }

    /**
     * 管理端列表：keyword 模糊匹配名称或编码（% 与 _ 按字面），status 精确过滤（空串不过滤）；id 倒序。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getAdminList(array $params, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query();

        $keyword = trim((string) ($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $like = Like::contains($keyword);
            $name = $this->qualify('name');
            $code = $this->qualify('code');
            $query->where(static function (Builder $q) use ($like, $name, $code): void {
                $q->where($name, 'like', $like)->orWhere($code, 'like', $like);
            });
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where($this->qualify('status'), (int) $params['status']);
        }

        $total = (clone $query)->count();
        $list = $this->applyOrder($query, 'id desc')->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /**
     * 覆盖基类：经模型实例 fill()->save() 写入，JSON 列（wechat_*_data、variables）的 array cast 才生效——
     * 基类 update() 走 Builder::update()，不套 cast。不存在（含软删）返回 false。
     *
     * @param array<string, mixed> $data
     */
    public function update(int|string $id, array $data): bool
    {
        $instance = $this->query()->where($this->qualify('id'), $id)->first();
        if ($instance === null) {
            return false;
        }
        $instance->fill($data);

        return $instance->save();
    }
}
