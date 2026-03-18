<?php
declare(strict_types=1);

namespace app\api\controller\v1\auth;

use core\base\Controller;
use core\auth\TokenManager;
use app\service\user\UserService;
use app\service\wechat\MiniAppService;
use think\Response;

class AuthController extends Controller
{
    protected UserService $userService;
    protected MiniAppService $miniAppService;
    protected TokenManager $tokenManager;

    /**
     * 账号+密码登录（账号可以是手机号或用户名）
     */
    public function login(): Response
    {
        try {
            $account = (string)$this->request->param('account', '');
            // 兼容旧版 mobile 参数
            if (empty($account)) {
                $account = (string)$this->request->param('mobile', '');
            }
            $password = (string)$this->request->param('password', '');

            if (empty($account) || empty($password)) {
                return $this->error(lang('business.mobile_password_required'));
            }

            $result = $this->userService->loginByPassword($account, $password, $this->request->ip());
            return $this->success(lang('messages.login_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 手机号+验证码登录
     */
    public function smsLogin(): Response
    {
        try {
            $mobile = (string)$this->request->param('mobile', '');
            $code = (string)$this->request->param('code', '');

            if (empty($mobile) || empty($code)) {
                return $this->error(lang('business.mobile_code_required'));
            }

            // TODO: 验证短信验证码（从缓存中比对）
            $cacheKey = 'sms_code:login:' . $mobile;
            $cachedCode = cache($cacheKey);
            if (!$cachedCode || $cachedCode !== $code) {
                return $this->error(lang('auth.captcha_invalid'));
            }

            $result = $this->userService->loginBySmsCode($mobile, $this->request->ip());

            // 清除验证码
            cache($cacheKey, null);

            return $this->success(lang('messages.login_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 微信小程序登录
     */
    public function wechatLogin(): Response
    {
        try {
            $code = (string)$this->request->param('code', '');
            if (empty($code)) {
                return $this->error(lang('business.missing_code_param'));
            }

            $session = $this->miniAppService->login($code);

            $result = $this->userService->loginByMiniApp(
                $session['openid'],
                $session['unionid'] ?? '',
                [],
                $this->request->ip()
            );

            return $this->success(lang('messages.login_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取当前用户信息
     */
    public function info(): Response
    {
        try {
            $userId = $this->getUserId();
            $userInfo = $this->userService->getUserInfo($userId);
            return $this->success(lang('messages.get_success'), $userInfo);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 退出登录
     */
    public function logout(): Response
    {
        try {
            $token = $this->tokenManager->getTokenFromHeader();
            if ($token) {
                $this->tokenManager->blacklist($token);
            }
            return $this->success(lang('messages.logout_success'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 注册（账号可以是手机号或用户名，无需验证码）
     */
    public function register(): Response
    {
        try {
            $account = (string)$this->request->param('account', '');
            // 兼容旧版 mobile 参数
            if (empty($account)) {
                $account = (string)$this->request->param('mobile', '');
            }
            $password = (string)$this->request->param('password', '');
            $passwordConfirmation = (string)$this->request->param('password_confirmation', '');

            if (empty($account) || empty($password)) {
                return $this->error(lang('business.register_fields_required'));
            }

            if ($password !== $passwordConfirmation) {
                return $this->error('两次输入的密码不一致');
            }

            $result = $this->userService->register($account, $password, $this->request->ip());

            return $this->success(lang('messages.register_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 刷新Token
     */
    public function refreshToken(): Response
    {
        try {
            $token = $this->tokenManager->getTokenFromHeader();
            if (!$token) {
                return $this->error(lang('messages.token_not_provided'));
            }
            $newToken = $this->tokenManager->refresh($token);
            return $this->success(lang('messages.refresh_success'), ['token' => $newToken]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
