<?php
declare(strict_types=1);

namespace app\repository\user;

use app\model\user\User;
use core\base\Repository;
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
        return User::findByMobile($mobile);
    }

    /**
     * 根据账号查找用户（手机号或用户名，统一查 mobile 字段）
     */
    public function findByAccount(string $account): ?Model
    {
        return User::findByMobile($account);
    }

    /**
     * 根据公众号/开放平台 openid 查找用户
     */
    public function findByOpenid(string $openid): ?Model
    {
        return User::findByOpenid($openid);
    }

    /**
     * 根据小程序 openid 查找用户
     */
    public function findByMiniOpenid(string $openid): ?Model
    {
        return User::findByMiniOpenid($openid);
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
        $query = User::order('id', 'desc');
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
        return User::where('nickname|mobile', 'like', "%{$keyword}%")->column('id');
    }

    /**
     * 查找用户并加行锁（FOR UPDATE）
     * 注意：返回 Model 实例（非数组），调用方使用 $user->balance 而非 $user['balance']
     */
    public function findForUpdate(int $id): ?User
    {
        return User::where('id', $id)->lock(true)->find();
    }

    /**
     * 根据公众号 openid 查找用户
     */
    public function findByOaOpenid(string $openid): ?Model
    {
        return User::where('oa_openid', $openid)->find();
    }
}
