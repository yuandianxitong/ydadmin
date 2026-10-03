<?php

declare(strict_types=1);

namespace app\api\controller\user;

use app\api\controller\AuthenticatedController;
use app\service\payment\RechargeService;
use app\service\user\UserService;
use app\service\wechat\WechatAuthService;
use app\service\wechat\WechatOaBindCookie;
use core\http\ClientIp;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Response;
use Webman\Http\Request;

/**
 * C 端会员自助（spec §6.2）。全部端点挂 ApiAuthMiddleware，身份取自 $request->userId
 * （中间件写入，不是 core\context\RequestContext——那是管理端概念）。C 端没有权限体系，
 * 全部方法标 #[PermissionSkip]。
 */
#[RouteGroup('/api/user')]
class UserController extends AuthenticatedController
{
    #[Inject]
    protected UserService $userService;

    #[Inject]
    protected RechargeService $rechargeService;

    #[Inject]
    protected WechatAuthService $wechatAuthService;

    #[Inject]
    protected WechatOaBindCookie $oaBindCookie;

    #[Get('/profile')]
    #[PermissionSkip]
    public function profile(Request $request): Response
    {
        return $this->success($this->userService->getProfile((int) $request->userId), lang('messages.get_success'));
    }

    #[Put('/profile')]
    #[PermissionSkip]
    public function updateProfile(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->updateProfileRules(), $this->updateProfileMessages());
        $this->userService->updateProfile((int) $request->userId, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Put('/change-password')]
    #[PermissionSkip]
    public function changePassword(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->changePasswordRules(), $this->changePasswordMessages());
        $this->userService->changePassword((int) $request->userId, (string) $data['old_password'], (string) $data['new_password']);

        return $this->success([], lang('messages.password_change_success'));
    }

    #[Get('/balance')]
    #[PermissionSkip]
    public function balance(Request $request): Response
    {
        return $this->success(['balance' => $this->userService->getBalance((int) $request->userId)], lang('messages.get_success'));
    }

    #[Get('/points')]
    #[PermissionSkip]
    public function points(Request $request): Response
    {
        return $this->success(['points' => $this->userService->getPoints((int) $request->userId)], lang('messages.get_success'));
    }

    #[Get('/balance-logs')]
    #[PermissionSkip]
    public function balanceLogs(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);

        return $this->paginate($this->userService->getBalanceLogs((int) $request->userId, $page, $limit));
    }

    #[Get('/points-logs')]
    #[PermissionSkip]
    public function pointsLogs(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);

        return $this->paginate($this->userService->getPointsLogs((int) $request->userId, $page, $limit));
    }

    /**
     * 余额充值（M5b spec §5.1）。端类型只看 X-Client-Type；客户端 IP 用 ClientIp::resolve()，
     * 不用 getRealIp()（直连地址是私网时它会信任客户端伪造的 X-Forwarded-For）。
     */
    #[Post('/recharge')]
    #[PermissionSkip]
    public function recharge(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->rechargeRules(), $this->rechargeMessages());

        $result = $this->rechargeService->recharge(
            (int) $request->userId,
            (string) $data['amount'],
            (string) $data['channel'],
            trim((string) ($request->header('x-client-type') ?? '')),
            ClientIp::resolve($request),
        );

        return $this->success($result, lang('messages.success'));
    }

    /**
     * POST /api/user/bind-oa-openid（spec §4.8）。openid 的可信来源是 HttpOnly cookie，不是请求体。
     */
    #[Post('/bind-oa-openid')]
    #[PermissionSkip]
    public function bindOaOpenid(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->bindOaOpenidRules(), $this->bindOaOpenidMessages());
        $proof = $this->oaBindCookie->verify((string) $request->cookie(WechatOaBindCookie::NAME, ''));
        $this->wechatAuthService->bindOaOpenid((int) $request->userId, (string) $data['oa_openid'], $proof);

        return $this->oaBindCookie->forget($this->success([], lang('messages.success')));
    }

    /** @return array<string, string> */
    private function updateProfileRules(): array
    {
        return [
            'nickname' => 'sometimes|required|string|max:50',
            'avatar'   => 'sometimes|required|string|max:255',
            'gender'   => 'sometimes|required|integer|in:0,1,2',
            'birthday' => 'sometimes|nullable|date_format:Y-m-d',
        ];
    }

    /** @return array<string, string> */
    private function updateProfileMessages(): array
    {
        return [
            'nickname.required'    => 'validation.nickname_require',
            'nickname.string'      => 'validation.nickname_require',
            'nickname.max'         => 'validation.nickname_max',
            'avatar.required'      => 'validation.avatar_require',
            'avatar.string'        => 'validation.avatar_require',
            'avatar.max'           => 'validation.avatar_max',
            'gender.required'      => 'validation.gender_invalid',
            'gender.integer'       => 'validation.gender_invalid',
            'gender.in'            => 'validation.gender_invalid',
            'birthday.date_format' => 'validation.birthday_invalid',
        ];
    }

    /** @return array<string, string> */
    private function changePasswordRules(): array
    {
        return [
            'old_password' => 'required|string|min:6|max:20',
            'new_password' => 'required|string|min:6|max:20',
        ];
    }

    /** @return array<string, string> */
    private function changePasswordMessages(): array
    {
        return [
            'old_password.required' => 'validation.old_password_require',
            'old_password.min'      => 'validation.password_length',
            'old_password.max'      => 'validation.password_length',
            'new_password.required' => 'validation.new_password_require',
            'new_password.min'      => 'validation.password_length',
            'new_password.max'      => 'validation.password_length',
        ];
    }

    /**
     * decimal:0,2 与 numeric 都放过 +10、.5、10. 这类写法，Money::toCents() 不收——多一条 regex 从入口挡住，
     * 否则会漏成 500。
     *
     * @return array<string, string>
     */
    private function rechargeRules(): array
    {
        return [
            'amount'  => 'required|numeric|decimal:0,2|regex:/^\d+(\.\d{1,2})?$/|between:1,10000',
            'channel' => 'required|string|in:wechat,alipay',
        ];
    }

    /** @return array<string, string> */
    private function rechargeMessages(): array
    {
        return [
            'amount.required'  => 'validation.recharge_amount_invalid',
            'amount.numeric'   => 'validation.recharge_amount_invalid',
            'amount.decimal'   => 'validation.recharge_amount_invalid',
            'amount.regex'     => 'validation.recharge_amount_invalid',
            'amount.between'   => 'validation.recharge_amount_invalid',
            'channel.required' => 'validation.payment_channel_invalid',
            'channel.string'   => 'validation.payment_channel_invalid',
            'channel.in'       => 'validation.payment_channel_invalid',
        ];
    }

    /** @return array<string, string> */
    private function bindOaOpenidRules(): array
    {
        return ['oa_openid' => 'required|string|max:128'];
    }

    /** @return array<string, string> */
    private function bindOaOpenidMessages(): array
    {
        return [
            'oa_openid.required' => 'validation.oa_openid_require',
            'oa_openid.string'   => 'validation.oa_openid_require',
            'oa_openid.max'      => 'validation.oa_openid_require',
        ];
    }
}
