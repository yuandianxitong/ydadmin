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
}
