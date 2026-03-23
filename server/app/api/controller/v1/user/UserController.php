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
        try {
            $amount = (float) $this->request->post('amount');
            $channel = $this->request->post('channel', 'wechat');

            if ($amount < 1 || $amount > 10000) {
                return $this->error('充值金额范围为 1.00 ~ 10000.00 元');
            }

            $userId = $this->getUserId();
            $clientType = $this->getClientType();

            $payParams = [
                'subject'      => '余额充值',
                'body'         => '账户余额充值 ' . $amount . ' 元',
                'total_amount' => $amount,
                'user_id'      => $userId,
                'biz_type'     => 'recharge',
                'client_type'  => $clientType,
            ];

            if ($channel === 'wechat') {
                $resolved = $this->paymentService->resolveWechatPayParams($clientType, $userId);
                $payParams['trade_type'] = $resolved['trade_type'];
                $payParams['appid']      = $resolved['appid'];
                if ($resolved['openid']) {
                    $payParams['openid'] = $resolved['openid'];
                }
                if ($resolved['trade_type'] === 'h5') {
                    $payParams['client_ip'] = $this->request->ip();
                }
            }

            $result = $this->paymentService->createOrder($channel, $payParams);

            return $this->success('ok', $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 绑定公众号 openid 到当前用户
     */
    public function bindOaOpenid(): Response
    {
        try {
            $oaOpenid = (string)$this->request->post('oa_openid', '');
            if (empty($oaOpenid)) {
                return $this->error('缺少 oa_openid');
            }

            $userId = $this->getUserId();
            \app\model\user\User::where('id', $userId)->update(['oa_openid' => $oaOpenid]);

            return $this->success('ok');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
