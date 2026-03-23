<?php
declare(strict_types=1);

namespace app\service\user;

use app\repository\user\UserRepository;
use core\auth\TokenManager;
use core\base\Service;
use core\exception\BusinessException;

class UserService extends Service
{
    protected UserRepository $userRepository;
    protected TokenManager $tokenManager;
    protected \app\service\wechat\MiniAppService $miniAppService;
    protected \app\service\wechat\OfficialAccountService $officialAccountService;

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
     * 手机号+验证码登录（自动注册）
     */
    public function loginBySmsCode(string $mobile, string $ip = ''): array
    {
        $user = $this->userRepository->findByMobile($mobile);

        if (!$user) {
            // 自动注册
            $this->userRepository->create([
                'mobile'   => $mobile,
                'nickname' => '用户' . substr($mobile, -4),
                'status'   => 1,
            ]);
            $user = $this->userRepository->findByMobile($mobile);

            $this->trigger('user.register', ['user_id' => $user->id, 'mobile' => $mobile]);
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
                $user->mini_openid = $openid;
                $user->save();
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
                $user->openid = $openid;
                $user->save();
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
        $user = $this->userRepository->findModel($userId);
        if (!$user) {
            throw new BusinessException(lang('business.user_not_found'));
        }

        $allowFields = ['nickname', 'avatar', 'gender', 'birthday'];
        $updateData = array_intersect_key($data, array_flip($allowFields));

        if (empty($updateData)) {
            throw new BusinessException(lang('business.no_updatable_fields'));
        }

        foreach ($updateData as $key => $value) {
            $user->$key = $value;
        }

        return $user->save();
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

        $user->password = password_hash($newPassword, PASSWORD_DEFAULT);
        return $user->save();
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
                $user->mini_openid = $openid;
                $user->save();
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
            $user->mini_openid = $openid;
            if ($unionid && empty($user->unionid)) {
                $user->unionid = $unionid;
            }
            $user->save();
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
        // 更新登录信息
        $user->last_login_ip = $ip;
        $user->last_login_time = date('Y-m-d H:i:s');
        $user->login_count = $user->login_count + 1;
        $user->save();

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
                $user->oa_openid = $openid;
                $user->save();
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
