<?php
declare(strict_types=1);

namespace app\api\controller\v1\user;

use core\base\Controller;
use app\service\user\UserService;
use think\Response;

class UserController extends Controller
{
    protected UserService $userService;

    /**
     * 获取个人信息
     */
    public function profile(): Response
    {
        try {
            $userInfo = $this->userService->getUserInfo($this->getUserId());
            return $this->success(lang('messages.get_success'), $userInfo);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 更新个人信息
     */
    public function updateProfile(): Response
    {
        try {
            $data = $this->request->only(['nickname', 'avatar', 'gender', 'birthday']);
            $this->userService->updateProfile($this->getUserId(), $data);
            return $this->success(lang('messages.update_success'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 修改密码
     */
    public function changePassword(): Response
    {
        try {
            $oldPassword = (string)$this->request->param('old_password', '');
            $newPassword = (string)$this->request->param('new_password', '');

            if (empty($newPassword) || strlen($newPassword) < 6) {
                return $this->error(lang('business.password_min_length'));
            }

            $this->userService->changePassword($this->getUserId(), $oldPassword, $newPassword);
            return $this->success(lang('messages.password_change_success'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
