<?php
declare(strict_types=1);

namespace app\api\controller\v1\auth;

use core\base\Controller;
use core\auth\TokenManager;
use app\service\user\UserService;
use app\service\wechat\MiniAppService;
use think\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '认证', description: '用户登录、注册、Token管理')]
class AuthController extends Controller
{
    protected UserService $userService;
    protected MiniAppService $miniAppService;
    protected TokenManager $tokenManager;

    #[OA\Post(
        path: '/auth/login',
        summary: '账号密码登录',
        tags: ['认证'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['account', 'password'],
                properties: [
                    new OA\Property(property: 'account', type: 'string', description: '账号（手机号或用户名）'),
                    new OA\Property(property: 'password', type: 'string', description: '密码'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '登录成功，返回token', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '账号或密码错误', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
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

    #[OA\Post(
        path: '/auth/sms-login',
        summary: '手机号验证码登录',
        tags: ['认证'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['mobile', 'code'],
                properties: [
                    new OA\Property(property: 'mobile', type: 'string', description: '手机号'),
                    new OA\Property(property: 'code', type: 'string', description: '短信验证码'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '登录成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '验证码错误', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
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

    #[OA\Post(
        path: '/auth/wechat-login',
        summary: '微信小程序登录',
        tags: ['认证'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', description: '微信小程序 code'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '登录成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
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

    #[OA\Post(
        path: '/auth/wechat-web-login',
        summary: '微信开放平台网页扫码登录（PC端）',
        tags: ['认证'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', description: '微信授权 code'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '登录成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
    public function wechatWebLogin(): Response
    {
        try {
            $code = (string)$this->request->param('code', '');
            if (empty($code)) {
                return $this->error(lang('business.missing_code_param'));
            }

            $result = $this->userService->loginByWechatOpenPlatformCode($code, $this->request->ip());
            return $this->success(lang('messages.login_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/auth/info',
        summary: '获取当前用户信息',
        security: [['bearerAuth' => []]],
        tags: ['认证'],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
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

    #[OA\Post(
        path: '/auth/logout',
        summary: '退出登录',
        security: [['bearerAuth' => []]],
        tags: ['认证'],
        responses: [
            new OA\Response(response: 200, description: '退出成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
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

    #[OA\Post(
        path: '/auth/register',
        summary: '注册（手机号+验证码+密码）',
        tags: ['认证'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['mobile', 'code', 'password'],
                properties: [
                    new OA\Property(property: 'mobile', type: 'string', description: '手机号'),
                    new OA\Property(property: 'code', type: 'string', description: '短信验证码'),
                    new OA\Property(property: 'password', type: 'string', description: '密码'),
                    new OA\Property(property: 'password_confirmation', type: 'string', description: '确认密码'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '注册成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '注册失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function register(): Response
    {
        try {
            $mobile = (string)$this->request->param('mobile', '');
            // 兼容旧版 account 参数
            if (empty($mobile)) {
                $mobile = (string)$this->request->param('account', '');
            }
            $code = (string)$this->request->param('code', '');
            $password = (string)$this->request->param('password', '');
            $passwordConfirmation = (string)$this->request->param('password_confirmation', '');

            if (empty($mobile) || empty($code) || empty($password)) {
                return $this->error(lang('business.register_fields_required'));
            }

            if ($password !== $passwordConfirmation) {
                return $this->error('两次输入的密码不一致');
            }

            // 校验短信验证码
            $cacheKey = 'sms_code:register:' . $mobile;
            $cachedCode = cache($cacheKey);
            if (!$cachedCode || $cachedCode !== $code) {
                return $this->error(lang('auth.captcha_invalid'));
            }

            $result = $this->userService->register($mobile, $password, $this->request->ip());

            // 清除已使用的验证码
            cache($cacheKey, null);

            return $this->success(lang('messages.register_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/auth/refresh-token',
        summary: '刷新 Token',
        security: [['bearerAuth' => []]],
        tags: ['认证'],
        responses: [
            new OA\Response(response: 200, description: '刷新成功，返回新 token', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
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

    #[OA\Post(
        path: '/auth/wechat-quick-login',
        summary: '微信快捷登录（小程序）',
        tags: ['认证'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', description: '微信小程序 code'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '登录成功或返回临时 token 待绑定手机号', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
    public function wechatQuickLogin(): Response
    {
        try {
            $code = (string)$this->request->post('code', '');
            if (empty($code)) {
                return $this->error('缺少 code');
            }
            $result = $this->userService->wechatQuickLogin($code, $this->request->ip());
            return $this->success('ok', $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/auth/wechat-bindphone',
        summary: '微信快捷登录 — 绑定手机号',
        tags: ['认证'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['temp_token', 'phone_code'],
                properties: [
                    new OA\Property(property: 'temp_token', type: 'string', description: '快捷登录返回的临时 token'),
                    new OA\Property(property: 'phone_code', type: 'string', description: '微信 getPhoneNumber code'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '绑定并登录成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
    public function wechatBindPhone(): Response
    {
        try {
            $tempToken = (string)$this->request->post('temp_token', '');
            $phoneCode = (string)$this->request->post('phone_code', '');
            if (empty($tempToken) || empty($phoneCode)) {
                return $this->error('参数不完整');
            }
            $result = $this->userService->wechatBindPhone($tempToken, $phoneCode, $this->request->ip());
            return $this->success('ok', $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/auth/wechat-h5-login',
        summary: '微信 H5 登录（公众号 OAuth code 换登录）',
        tags: ['认证'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', description: '公众号 OAuth code'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '登录成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
    public function wechatH5Login(): Response
    {
        try {
            $code = (string)$this->request->post('code', '');
            if (empty($code)) {
                return $this->error('缺少 code');
            }
            $result = $this->userService->wechatH5Login($code, $this->request->ip());
            return $this->success('ok', $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
