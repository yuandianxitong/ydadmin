<?php
declare(strict_types=1);

namespace app\api\controller\v1\user;

use core\base\Controller;
use app\service\user\UserService;
use think\Response;

class UserController extends Controller
{
    protected UserService $userService;
    protected \app\service\user\UserManageService $userManageService;
    protected \app\service\payment\PaymentService $paymentService;

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

    /**
     * 获取余额
     */
    public function balance(): Response
    {
        $userId = $this->getUserId();
        $result = $this->userManageService->getUserBalance($userId);
        return $this->success('ok', $result);
    }

    /**
     * 获取余额记录
     */
    public function balanceLogs(): Response
    {
        $userId = $this->getUserId();
        $params = $this->getRequestData();
        $result = $this->userManageService->getUserBalanceLogs($userId, $params);
        return $this->success('ok', $result);
    }

    /**
     * 获取积分
     */
    public function points(): Response
    {
        $userId = $this->getUserId();
        $result = $this->userManageService->getUserPoints($userId);
        return $this->success('ok', $result);
    }

    /**
     * 获取积分记录
     */
    public function pointsLogs(): Response
    {
        $userId = $this->getUserId();
        $params = $this->getRequestData();
        $result = $this->userManageService->getUserPointsLogs($userId, $params);
        return $this->success('ok', $result);
    }

    /**
     * 余额充值
     */
    public function recharge(): Response
    {
        $amount = (float) $this->request->post('amount');
        $channel = $this->request->post('channel', 'wechat');

        if ($amount < 1 || $amount > 10000) {
            return $this->error('充值金额范围为 1.00 ~ 10000.00 元');
        }

        $userId = $this->getUserId();

        $result = $this->paymentService->createOrder($channel, [
            'subject'      => '余额充值',
            'body'         => '账户余额充值 ' . $amount . ' 元',
            'total_amount' => $amount,
            'user_id'      => $userId,
            'biz_type'     => 'recharge',
        ]);

        return $this->success('ok', $result);
    }
}
