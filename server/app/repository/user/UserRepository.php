<?php

declare(strict_types=1);

namespace app\repository\user;

use app\model\user\User;
use core\base\Model;
use core\base\Repository;
use core\support\Like;

/**
 * 会员仓储（users 表，spec §8）：不设 $dataScoped——users 没有 created_by 也没有部门列，一旦声明
 * 数据权限，管理端会员列表会按不存在的列过滤（轻则全空、重则 SQL 报错）。C 端的安全性靠查询显式
 * 带 user_id 条件，不靠数据范围；红线 Test22（三表不受数据权限影响）属于 Task 11。
 */
class UserRepository extends Repository
{
    /** @var list<string> */
    protected array $sortable = ['id', 'created_at', 'login_count'];

    protected function getModel(): Model
    {
        return new User();
    }

    /**
     * 按手机号查未删除的会员（登录账号目前只支持手机号，spec §4.3；含 password，仅供登录校验——
     * 模型 $hidden 挡了 toArray() 里的 password，这里显式 makeVisible 找回，同 AdminRepository::findByUsername()）。
     *
     * @return array<string, mixed>|null
     */
    public function findByAccount(string $account): ?array
    {
        return $this->query()->where($this->qualify('mobile'), $account)->first()?->makeVisible('password')->toArray();
    }

    /**
     * 按 id 查找（含 password，仅供修改密码时校验旧密码）。users 不受数据权限约束（spec §8），
     * 不需要像 AdminRepository::findWithPassword() 那样包一层 DataScope::bypass()。
     *
     * @return array<string, mixed>|null
     */
    public function findWithPassword(int $id): ?array
    {
        return $this->query()->where($this->qualify('id'), $id)->first()?->makeVisible('password')->toArray();
    }

    /**
     * 事务内行锁读——封在 Repository 层，Service 不碰 Builder（spec §5.2）。
     *
     * 注：lockForUpdate() 未在 Eloquent\Builder 上原生声明（经 @mixin 转发给底层
     * Query\Builder），若直接链式调用会让静态分析把之后 first() 的返回类型误判为
     * stdClass。lockForUpdate() 是原地修改同一个查询对象（无需使用其返回值），因此
     * 拆成单独语句调用，$query 的静态类型保持为 Eloquent\Builder，first() 仍正确
     * 解析为 Model|null（同范式见元点SaaS `PaymentOrderRepository::findByOrderNoForUpdate`）。
     *
     * @return array<string, mixed>|null
     */
    public function findForUpdate(int $id): ?array
    {
        $query = $this->query()->where($this->qualify('id'), $id);
        $query->lockForUpdate();
        $row = $query->first();

        return $row === null ? null : $row->toArray();
    }

    /** 登录成功：IP、时间与登录次数一次更新（spec §4.3 loginSuccess）。 */
    public function updateLastLogin(int $id, string $ip): bool
    {
        return $this->query()->where($this->qualify('id'), $id)->increment('login_count', 1, [
            'last_login_ip'   => $ip,
            'last_login_time' => date('Y-m-d H:i:s'),
        ]) > 0;
    }

    public function mobileExists(string $mobile): bool
    {
        return $this->query()->where($this->qualify('mobile'), $mobile)->exists();
    }

    /**
     * 管理端会员列表：keyword（nickname/mobile 模糊）、status。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getManageList(array $params, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query();

        $keyword = trim((string) ($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $like = Like::contains($keyword);
            $query->where(function ($q) use ($like): void {
                $q->where($this->qualify('nickname'), 'like', $like)->orWhere($this->qualify('mobile'), 'like', $like);
            });
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where($this->qualify('status'), (int) $params['status']);
        }

        $total = (clone $query)->count();
        $list = $query->orderBy($this->qualify('id'), 'desc')->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }

    public function countAll(): int
    {
        return $this->query()->count();
    }

    public function countCreatedBetween(string $start, string $end): int
    {
        return $this->query()->whereBetween($this->qualify('created_at'), [$start, $end])->count();
    }

    /** 最近登录时间不早于 $since 的会员数（仪表盘「活跃用户」，spec §10）。 */
    public function countActiveSince(string $since): int
    {
        return $this->query()->where($this->qualify('last_login_time'), '>=', $since)->count();
    }

    /**
     * 最近 $days 天每天注册数，补零，日期升序（仪表盘注册趋势，spec §10）。
     *
     * @return list<array{date: string, count: int}>
     */
    public function registerTrend(int $days): array
    {
        $days = max(1, $days);
        $start = new \DateTimeImmutable('today');
        $start = $start->modify('-' . ($days - 1) . ' days');

        $rows = $this->query()
            ->selectRaw('DATE(' . $this->qualify('created_at') . ') as reg_date, COUNT(*) as reg_count')
            ->where($this->qualify('created_at'), '>=', $start->format('Y-m-d 00:00:00'))
            ->groupBy('reg_date')
            ->pluck('reg_count', 'reg_date');

        $trend = [];
        for ($i = 0; $i < $days; $i++) {
            // 输出的 date 是 m-d（与 loginTrend 逐字一致，两条趋势共用工作台同一条横轴），
            // 但查 $rows 必须用 Y-m-d——SQL 的 DATE() 出来就是这个格式，两者不能共用一个变量。
            $day = $start->modify("+{$i} days");
            $key = $day->format('Y-m-d');
            $trend[] = ['date' => $day->format('m-d'), 'count' => (int) ($rows[$key] ?? 0)];
        }

        return $trend;
    }
}
