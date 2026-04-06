<?php
declare(strict_types=1);

namespace app\repository\user;

use app\model\user\User;
use core\base\Repository;
use think\facade\Db;
use think\Model;

class UserRepository extends Repository
{
    protected function getModel(): Model
    {
        return new User();
    }

    /**
     * 根据手机号查找用户
     */
    public function findByMobile(string $mobile): ?Model
    {
        return $this->model->where('mobile', $mobile)->find();
    }

    /**
     * 根据账号查找用户（手机号或用户名，统一查 mobile 字段）
     */
    public function findByAccount(string $account): ?Model
    {
        return $this->findByMobile($account);
    }

    /**
     * 根据公众号/开放平台 openid 查找用户
     */
    public function findByOpenid(string $openid): ?Model
    {
        return $this->model->where('openid', $openid)->find();
    }

    /**
     * 根据小程序 openid 查找用户
     */
    public function findByMiniOpenid(string $openid): ?Model
    {
        return $this->model->where('mini_openid', $openid)->find();
    }

    /**
     * 根据 unionid 查找用户
     */
    public function findByUnionid(string $unionid): ?Model
    {
        return $this->model->where('unionid', $unionid)->find();
    }

    /**
     * 获取用户模型实例（用于更新操作）
     */
    public function findModel(int $id): ?Model
    {
        return $this->model->find($id);
    }

    /**
     * 获取用户模型并暴露密码字段（用于密码校验）
     */
    public function findModelWithPassword(int $id): ?Model
    {
        $user = $this->model->find($id);
        if ($user) {
            $user->hidden([]);
        }
        return $user;
    }

    /**
     * 用户列表（admin 后台用）
     */
    public function getSearchList(array $params, int $page = 1, int $limit = 20): array
    {
        $query = $this->model->order('id', 'desc');
        if (!empty($params['keyword'])) {
            $query->where('nickname|mobile', 'like', "%{$params['keyword']}%");
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', $params['status']);
        }
        $total = $query->count();
        $list = $query->page($page, $limit)
            ->field('id,nickname,avatar,mobile,balance,points,status,last_login_ip,last_login_time,login_count,created_at')
            ->select()->toArray();
        return ['list' => $list, 'total' => $total];
    }

    /**
     * 根据昵称或手机号模糊搜索用户ID
     */
    public function searchIdsByKeyword(string $keyword): array
    {
        return $this->model->where('nickname|mobile', 'like', "%{$keyword}%")->column('id');
    }

    /**
     * 查找用户并加行锁（FOR UPDATE）
     * 注意：返回 Model 实例（非数组），调用方使用 $user->balance 而非 $user['balance']
     */
    public function findForUpdate(int $id): ?User
    {
        /** @var User|null $user */
        $user = $this->model->where('id', $id)->lock(true)->find();
        return $user;
    }

    /**
     * 根据公众号 openid 查找用户
     */
    public function findByOaOpenid(string $openid): ?Model
    {
        return $this->model->where('oa_openid', $openid)->find();
    }

    /**
     * 更新用户的公众号 openid
     */
    public function updateOaOpenid(int $userId, string $oaOpenid): bool
    {
        return $this->model->where('id', $userId)->update(['oa_openid' => $oaOpenid]) !== false;
    }

    /**
     * 更新用户的公众号 openid 和 unionid
     */
    public function updateOaOpenidAndUnionid(int $userId, string $oaOpenid, ?string $unionid): bool
    {
        $data = ['oa_openid' => $oaOpenid];
        if ($unionid !== null) {
            $data['unionid'] = $unionid ?: null;
        }
        return $this->model->where('id', $userId)->update($data) !== false;
    }

    /**
     * 将小程序 openid 关联到指定用户（通常在通过 unionid 匹配后调用）
     */
    public function bindMiniOpenid(int $userId, string $openid, ?string $unionid = null): bool
    {
        $data = ['mini_openid' => $openid];
        if ($unionid !== null && $unionid !== '') {
            $data['unionid'] = $unionid;
        }
        return $this->model->where('id', $userId)->update($data) !== false;
    }

    /**
     * 将开放平台/公众号 openid 关联到指定用户
     */
    public function bindOpenid(int $userId, string $openid): bool
    {
        return $this->model->where('id', $userId)->update(['openid' => $openid]) !== false;
    }

    /**
     * 登录成功后更新最后登录信息（ip、时间、次数）
     *
     * 使用 `Db::raw()` 表达式实现 `login_count = login_count + 1` 原子自增，
     * 避免读取-修改-写入的竞态。
     */
    public function updateLastLogin(int $userId, string $ip): bool
    {
        return $this->model->where('id', $userId)->update([
            'last_login_ip'   => $ip,
            'last_login_time' => date('Y-m-d H:i:s'),
            'login_count'     => Db::raw('login_count + 1'),
        ]) !== false;
    }

    /**
     * 根据 ID 获取指定字段的值
     */
    public function getFieldById(int $userId, string $field): mixed
    {
        $user = $this->model->where('id', $userId)->field($field)->find();
        return $user?->$field;
    }

    /**
     * 用户总数
     */
    public function getTotalCount(): int
    {
        return $this->model->count();
    }

    /**
     * 今日新增用户数
     */
    public function getTodayNewCount(): int
    {
        return $this->model->whereTime('created_at', 'today')->count();
    }

    /**
     * 上周同日新增用户数
     */
    public function getLastWeekSameDayNewCount(): int
    {
        $date = date('Y-m-d', strtotime('-7 days'));
        return $this->model->whereDay('created_at', $date)->count();
    }

    /**
     * 活跃用户数（最近7天有登录记录）
     */
    public function getActiveCount(int $days = 7): int
    {
        $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        return $this->model->where('last_login_time', '>=', $startDate)->count();
    }

    /**
     * 上周活跃用户数（用于环比对比）
     */
    public function getLastWeekActiveCount(): int
    {
        $lastWeekStart = date('Y-m-d H:i:s', strtotime('-14 days'));
        $lastWeekEnd = date('Y-m-d H:i:s', strtotime('-7 days'));
        return $this->model->where('last_login_time', '>=', $lastWeekStart)
            ->where('last_login_time', '<', $lastWeekEnd)
            ->count();
    }

    /**
     * 用户注册趋势（最近N天）
     */
    public function getRegisterTrend(int $days = 7): array
    {
        $startDate = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));
        $rows = $this->model->where('created_at', '>=', $startDate . ' 00:00:00')
            ->fieldRaw("DATE(created_at) as date, COUNT(*) as count")
            ->group('date')
            ->select()
            ->toArray();

        $countMap = [];
        foreach ($rows as $row) {
            $countMap[$row['date']] = (int)$row['count'];
        }

        $trend = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $trend[] = [
                'date'  => date('m-d', strtotime($date)),
                'count' => $countMap[$date] ?? 0,
            ];
        }
        return $trend;
    }
}
