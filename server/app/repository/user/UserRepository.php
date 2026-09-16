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
    /**
     * 会员表上可按 openid 登录的三列（M6a 设计决定 6）：openid=开放平台/PC 扫码、mini_openid=小程序、
     * oa_openid=公众号。列名会拼进 SQL，只认这三个，调用方传别的一律 \InvalidArgumentException。
     */
    public const WECHAT_COLUMNS = ['openid', 'mini_openid', 'oa_openid'];

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
     * 按本端 openid 列查未软删会员（M6a spec §4.1 第 1 步）。重复时取 id 最小的一条——openid 列只有普通索引，
     * 历史脏数据可能重复，取最早注册的那个保证结果稳定。
     *
     * @return array<string, mixed>|null
     */
    public function findByWechatColumn(string $column, string $openid): ?array
    {
        $this->assertWechatColumn($column);
        $this->assertIdentifier($openid, 'openid');

        $query = $this->query()->where($this->qualify($column), $openid);
        $query->orderBy($this->qualify('id'));

        return $query->first()?->toArray();
    }

    /**
     * 按 unionid 查未软删会员（spec §4.1 第 2 步），重复时取 id 最小。
     *
     * @return array<string, mixed>|null
     */
    public function findByUnionid(string $unionid): ?array
    {
        $this->assertIdentifier($unionid, 'unionid');

        $query = $this->query()->where($this->qualify('unionid'), $unionid);
        $query->orderBy($this->qualify('id'));

        return $query->first()?->toArray();
    }

    /**
     * 只在该列为空时写入（设计决定 6「匹配不覆盖」）：条件更新把「查空 + 写入」合成一条 SQL，
     * 并发下不会把别人刚写进去的值覆盖掉。软删会员经 query() 的全局作用域自动排除。
     */
    public function bindWechatColumnIfEmpty(int $userId, string $column, string $openid): bool
    {
        $this->assertWechatColumn($column);
        $this->assertIdentifier($openid, 'openid');

        return $this->query()
            ->where($this->qualify('id'), $userId)
            ->whereNull($this->qualify($column))
            ->update([$column => $openid]) > 0;
    }

    /** unionid 同理：只补空列，不覆盖。 */
    public function fillUnionidIfEmpty(int $userId, string $unionid): bool
    {
        $this->assertIdentifier($unionid, 'unionid');

        return $this->query()
            ->where($this->qualify('id'), $userId)
            ->whereNull($this->qualify('unionid'))
            ->update(['unionid' => $unionid]) > 0;
    }

    /**
     * 微信登录注册（spec §4.2–4.5）：只写本端 openid 列，其余两列留空；没有口令（password 为 NULL，
     * 这类会员不能走账号密码登录，loginByPassword 的 password_verify 对 NULL 哈希恒为 false）。
     */
    public function createWechatUser(string $column, string $openid, ?string $unionid, string $nickname, ?string $avatar, ?string $mobile = null): int
    {
        $this->assertWechatColumn($column);
        $this->assertIdentifier($openid, 'openid');
        if ($unionid !== null) {
            $this->assertIdentifier($unionid, 'unionid');
        }

        $created = $this->create([
            $column    => $openid,
            'unionid'  => $unionid,
            'nickname' => $nickname,
            'avatar'   => $avatar,
            'mobile'   => $mobile,
            'status'   => 1,
        ]);

        return (int) $created['id'];
    }

    private function assertWechatColumn(string $column): void
    {
        if (!in_array($column, self::WECHAT_COLUMNS, true)) {
            throw new \InvalidArgumentException("不支持的微信身份列：{$column}");
        }
    }

    private function assertIdentifier(string $value, string $name): void
    {
        if ($value === '') {
            throw new \InvalidArgumentException("{$name} 不能为空");
        }
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
