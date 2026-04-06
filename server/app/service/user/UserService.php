<?php
declare(strict_types=1);

namespace app\service\user;

use app\repository\system\SystemConfigRepository;
use app\repository\user\UserRepository;
use core\auth\TokenManager;
use core\base\Service;
use core\exception\BusinessException;
use GuzzleHttp\Client as HttpClient;

class UserService extends Service
{
    protected UserRepository $userRepository;
    protected SystemConfigRepository $systemConfigRepository;
    protected TokenManager $tokenManager;
    protected \app\service\wechat\MiniAppService $miniAppService;
    protected \app\service\wechat\OfficialAccountService $officialAccountService;

    /**
     * 检查手机号是否已注册
     */
    public function mobileExists(string $mobile): bool
    {
        return $this->userRepository->findByMobile($mobile) !== null;
    }

    /**
     * 账号+密码登录（账号可以是手机号或用户名）
     */
    public function loginByPassword(string $account, string $password, string $ip = ''): array
    {
        $user = $this->userRepository->findByAccount($account);
        if (!$user) {
            throw new BusinessException(lang('business.user_not_found'));
        }

        if ($user->status !== 1) {
            throw new BusinessException(lang('business.user_account_disabled'));
        }

        if (!password_verify($password, $user->password)) {
            throw new BusinessException(lang('business.user_password_error'));
        }

        return $this->loginSuccess($user, $ip);
    }

    /**
     * 手机号+验证码登录
     */
    public function loginBySmsCode(string $mobile, string $ip = ''): array
    {
        $user = $this->userRepository->findByMobile($mobile);

        if (!$user) {
            throw new BusinessException(lang('business.mobile_not_registered'));
        }

        if ($user->status !== 1) {
            throw new BusinessException(lang('business.user_account_disabled'));
        }

        return $this->loginSuccess($user, $ip);
    }

    /**
     * 微信小程序登录
     */
    public function loginByMiniApp(string $openid, string $unionid = '', array $userInfo = [], string $ip = ''): array
    {
        $user = $this->userRepository->findByMiniOpenid($openid);

        if (!$user && $unionid) {
            // 尝试通过 unionid 关联已有账号
            $user = $this->userRepository->findByUnionid($unionid);
            if ($user) {
                $this->userRepository->bindMiniOpenid((int) $user->id, $openid);
                $user = $this->userRepository->findByMiniOpenid($openid);
            }
        }

        if (!$user) {
            // 自动注册
            $this->userRepository->create([
                'mini_openid' => $openid,
                'unionid'     => $unionid ?: null,
                'nickname'    => $userInfo['nickname'] ?? '微信用户',
                'avatar'      => $userInfo['avatar'] ?? '',
                'status'      => 1,
            ]);
            $user = $this->userRepository->findByMiniOpenid($openid);

            $this->trigger('user.register', ['user_id' => $user->id, 'channel' => 'miniapp']);
        }

        if ($user->status !== 1) {
            throw new BusinessException(lang('business.user_account_disabled'));
        }

        return $this->loginSuccess($user, $ip);
    }

    /**
     * 注册（账号可以是手机号或用户名）
     */
    public function register(string $account, string $password, string $ip = ''): array
    {
        $isMobile = preg_match('/^1[3-9]\d{9}$/', $account);

        // 检查账号是否已注册
        $existing = $this->userRepository->findByAccount($account);
        if ($existing) {
            throw new BusinessException($isMobile ? lang('business.mobile_already_registered') : '该账号已被注册');
        }

        $this->userRepository->create([
            'mobile'   => $account,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'nickname' => $isMobile ? '用户' . substr($account, -4) : $account,
            'status'   => 1,
        ]);

        $user = $this->userRepository->findByAccount($account);

        $this->trigger('user.register', ['user_id' => $user->id, 'account' => $account]);

        return $this->loginSuccess($user, $ip);
    }

    /**
     * 微信开放平台 code 换登录（PC 端扫码）
     *
     * 流程：读取开放平台配置 → 用 code 换 access_token 和 openid → 获取用户信息 → 调用 loginByWechatWeb
     */
    public function loginByWechatOpenPlatformCode(string $code, string $ip = ''): array
    {
        $appId     = (string) $this->systemConfigRepository->getConfigValue('wechat_open_app_id', '');
        $appSecret = (string) $this->systemConfigRepository->getConfigValue('wechat_open_app_secret', '');
        if ($appId === '' || $appSecret === '') {
            throw new BusinessException(lang('business.wechat_open_platform_not_configured'));
        }

        $http = new HttpClient(['timeout' => 5.0]);

        // 用 code 换取 access_token 和 openid
        $tokenRes = $http->get('https://api.weixin.qq.com/sns/oauth2/access_token', [
            'query' => [
                'appid'      => $appId,
                'secret'     => $appSecret,
                'code'       => $code,
                'grant_type' => 'authorization_code',
            ],
        ]);
        $tokenData = json_decode((string) $tokenRes->getBody(), true) ?: [];

        if (empty($tokenData['openid'])) {
            throw new BusinessException($tokenData['errmsg'] ?? lang('business.wechat_auth_failed'));
        }

        $openid  = (string) $tokenData['openid'];
        $unionid = (string) ($tokenData['unionid'] ?? '');
        $accessToken = (string) ($tokenData['access_token'] ?? '');

        // 获取用户资料（失败时降级为空）
        $wxUser = [];
        if ($accessToken !== '') {
            try {
                $userRes = $http->get('https://api.weixin.qq.com/sns/userinfo', [
                    'query' => [
                        'access_token' => $accessToken,
                        'openid'       => $openid,
                    ],
                ]);
                $wxUser = json_decode((string) $userRes->getBody(), true) ?: [];
            } catch (\Throwable $e) {
                $this->log('获取微信用户信息失败: ' . $e->getMessage(), [], 'warning');
            }
        }

        return $this->loginByWechatWeb($openid, $unionid, [
            'nickname' => (string) ($wxUser['nickname'] ?? ''),
            'avatar'   => (string) ($wxUser['headimgurl'] ?? ''),
        ], $ip);
    }

    /**
     * 微信开放平台网页登录（PC端扫码）
     */
    public function loginByWechatWeb(string $openid, string $unionid = '', array $userInfo = [], string $ip = ''): array
    {
        // 优先通过 openid 查找
        $user = $this->userRepository->findByOpenid($openid);

        // 通过 unionid 尝试关联已有账号
        if (!$user && $unionid) {
            $user = $this->userRepository->findByUnionid($unionid);
            if ($user && empty($user->openid)) {
                $this->userRepository->bindOpenid((int) $user->id, $openid);
                $user = $this->userRepository->findByOpenid($openid);
            }
        }

        if (!$user) {
            // 自动注册
            $this->userRepository->create([
                'openid'   => $openid,
                'unionid'  => $unionid ?: null,
                'nickname' => $userInfo['nickname'] ?: '微信用户',
                'avatar'   => $userInfo['avatar'] ?? '',
                'status'   => 1,
            ]);
            $user = $this->userRepository->findByOpenid($openid);

            $this->trigger('user.register', ['user_id' => $user->id, 'channel' => 'wechat_web']);
        }

        if ($user->status !== 1) {
            throw new BusinessException(lang('business.user_account_disabled'));
        }

        return $this->loginSuccess($user, $ip);
    }

    /**
     * 获取用户信息
     */
    public function getUserInfo(int $userId): array
    {
        $user = $this->userRepository->find($userId);
        if (!$user) {
            throw new BusinessException(lang('business.user_not_found'));
        }

        return $user;
    }

    /**
     * 更新用户资料
     */
    public function updateProfile(int $userId, array $data): bool
    {
        $user = $this->userRepository->find($userId);
        if (!$user) {
            throw new BusinessException(lang('business.user_not_found'));
        }

        $allowFields = ['nickname', 'avatar', 'gender', 'birthday'];
        $updateData = array_intersect_key($data, array_flip($allowFields));

        if (empty($updateData)) {
            throw new BusinessException(lang('business.no_updatable_fields'));
        }

        return $this->userRepository->update($userId, $updateData);
    }

    /**
     * 修改密码
     */
    public function changePassword(int $userId, string $oldPassword, string $newPassword): bool
    {
        $user = $this->userRepository->findModelWithPassword($userId);
        if (!$user) {
            throw new BusinessException(lang('business.user_not_found'));
        }

        if ($user->password && !password_verify($oldPassword, $user->password)) {
            throw new BusinessException(lang('business.user_old_password_error'));
        }

        return $this->userRepository->update($userId, [
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
        ]);
    }

    /**
     * 微信快捷登录（小程序）— 通过 code 获取 openid，已注册直接登录
     */
    public function wechatQuickLogin(string $code, string $ip = ''): array
    {
        $session = $this->miniAppService->login($code);
        $openid = $session['openid'] ?? '';
        $unionid = $session['unionid'] ?? '';

        // 按 mini_openid 查找
        $user = $this->userRepository->findByMiniOpenid($openid);

        // unionid 关联
        if (!$user && $unionid) {
            $user = $this->userRepository->findByUnionid($unionid);
            if ($user && empty($user->mini_openid)) {
                $this->userRepository->bindMiniOpenid((int) $user->id, $openid);
                $user = $this->userRepository->findByMiniOpenid($openid);
            }
        }

        if ($user) {
            if ($user->status !== 1) {
                throw new BusinessException(lang('business.user_account_disabled'));
            }
            return array_merge(['status' => 'logged_in'], $this->loginSuccess($user, $ip));
        }

        // 未找到用户，需要手机号绑定
        $tempToken = md5(uniqid((string)mt_rand(), true));
        cache('wechat_quick_' . $tempToken, [
            'openid'  => $openid,
            'unionid' => $unionid,
        ], 300);

        return [
            'status'     => 'need_bindphone',
            'temp_token' => $tempToken,
        ];
    }

    /**
     * 微信快捷登录 — 绑定手机号完成注册
     */
    public function wechatBindPhone(string $tempToken, string $phoneCode, string $ip = ''): array
    {
        $wechatData = cache('wechat_quick_' . $tempToken);
        if (empty($wechatData)) {
            throw new BusinessException('登录已过期，请重新操作');
        }
        cache('wechat_quick_' . $tempToken, null);

        $openid = $wechatData['openid'];
        $unionid = $wechatData['unionid'] ?? '';

        // 解密手机号
        $phoneInfo = $this->miniAppService->decryptPhoneNumber($phoneCode);
        $mobile = $phoneInfo['pure_phone_number'] ?: ($phoneInfo['phone_number'] ?? '');
        if (empty($mobile)) {
            throw new BusinessException('获取手机号失败');
        }

        // 查找已有手机号用户
        $user = $this->userRepository->findByMobile($mobile);
        if ($user) {
            $bindUnionid = ($unionid && empty($user->unionid)) ? $unionid : null;
            $this->userRepository->bindMiniOpenid((int) $user->id, $openid, $bindUnionid);
            $user = $this->userRepository->findByMobile($mobile);
        } else {
            $this->userRepository->create([
                'mobile'      => $mobile,
                'mini_openid' => $openid,
                'unionid'     => $unionid ?: null,
                'nickname'    => '微信用户',
                'status'      => 1,
            ]);
            $user = $this->userRepository->findByMobile($mobile);
            $this->trigger('user.register', ['user_id' => $user->id, 'channel' => 'wechat_mini_quick']);
        }

        if ($user->status !== 1) {
            throw new BusinessException(lang('business.user_account_disabled'));
        }

        return array_merge(['status' => 'logged_in'], $this->loginSuccess($user, $ip));
    }

    /**
     * 登录成功处理
     */
    protected function loginSuccess($user, string $ip = ''): array
    {
        // 更新登录信息（走 Repository，避免在 Service 里直接操作 Model）
        $this->userRepository->updateLastLogin((int) $user->id, $ip);

        // 生成Token
        $token = $this->tokenManager->generate([
            'type'    => 'user',
            'user_id' => $user->id,
            'mobile'  => $user->mobile ?? '',
        ]);

        $this->trigger('user.login', ['user_id' => $user->id]);

        return [
            'token'     => $token,
            'user_info' => [
                'id'       => $user->id,
                'nickname' => $user->nickname,
                'avatar'   => $user->avatar,
                'mobile'   => $user->mobile,
            ],
        ];
    }

    /**
     * 绑定公众号 openid 到指定用户
     */
    public function bindOaOpenid(int $userId, string $oaOpenid): bool
    {
        return $this->userRepository->updateOaOpenid($userId, $oaOpenid);
    }

    /**
     * 绑定公众号 openid 和 unionid 到指定用户
     */
    public function bindOaOpenidAndUnionid(int $userId, string $oaOpenid, ?string $unionid): bool
    {
        return $this->userRepository->updateOaOpenidAndUnionid($userId, $oaOpenid, $unionid);
    }

    /**
     * 微信 H5 登录（公众号 OAuth）
     * @return array ['status' => 'logged_in'|'need_bindphone', 'token'?, 'openid', 'unionid']
     */
    public function wechatH5Login(string $code, string $ip = ''): array
    {
        $wechatUser = $this->officialAccountService->getUserByCode($code);
        $openid = $wechatUser['openid'] ?? '';
        $unionid = $wechatUser['unionid'] ?? '';

        if (empty($openid)) {
            throw new BusinessException('微信授权失败，未获取到 openid');
        }

        // 按 oa_openid 查找
        $user = $this->userRepository->findByOaOpenid($openid);

        // unionid 关联
        if (!$user && $unionid) {
            $user = $this->userRepository->findByUnionid($unionid);
            if ($user && empty($user->oa_openid)) {
                $this->userRepository->updateOaOpenid((int) $user->id, $openid);
                $user = $this->userRepository->findByOaOpenid($openid);
            }
        }

        if ($user) {
            if ($user->status !== 1) {
                throw new BusinessException(lang('business.user_account_disabled'));
            }
            return array_merge([
                'status'  => 'logged_in',
                'openid'  => $openid,
                'unionid' => $unionid,
            ], $this->loginSuccess($user, $ip));
        }

        // 未找到用户，缓存 openid 供手机号绑定使用
        $tempToken = md5(uniqid((string)mt_rand(), true));
        cache('wechat_h5_' . $tempToken, [
            'openid'   => $openid,
            'unionid'  => $unionid,
            'nickname' => $wechatUser['nickname'] ?? '',
            'avatar'   => $wechatUser['avatar'] ?? '',
        ], 300);

        return [
            'status'     => 'need_login',
            'temp_token' => $tempToken,
            'openid'     => $openid,
            'unionid'    => $unionid,
        ];
    }
}
