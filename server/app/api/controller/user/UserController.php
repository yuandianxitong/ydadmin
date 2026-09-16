<?php

declare(strict_types=1);

namespace app\api\controller\user;

use app\service\user\UserService;
use core\base\Controller;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * C 端会员自助（spec §6.2）。全部端点挂 ApiAuthMiddleware，身份取自 $request->userId
 * （中间件写入，不是 core\context\RequestContext——那是管理端概念）。C 端没有权限体系，
 * 全部方法标 #[PermissionSkip]。
 */
class UserController extends Controller
{
    #[Inject]
    protected UserService $userService;

    #[PermissionSkip]
    public function profile(Request $request): Response
    {
        return $this->success($this->userService->getProfile((int) $request->userId), lang('messages.get_success'));
    }

    #[PermissionSkip]
    public function updateProfile(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->updateProfileRules(), $this->updateProfileMessages());
        $this->userService->updateProfile((int) $request->userId, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[PermissionSkip]
    public function changePassword(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->changePasswordRules(), $this->changePasswordMessages());
        $this->userService->changePassword((int) $request->userId, (string) $data['old_password'], (string) $data['new_password']);

        return $this->success([], lang('messages.password_change_success'));
    }

    #[PermissionSkip]
    public function balance(Request $request): Response
    {
        return $this->success(['balance' => $this->userService->getBalance((int) $request->userId)], lang('messages.get_success'));
    }

    #[PermissionSkip]
    public function points(Request $request): Response
    {
        return $this->success(['points' => $this->userService->getPoints((int) $request->userId)], lang('messages.get_success'));
    }

    #[PermissionSkip]
    public function balanceLogs(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);

        return $this->paginate($this->userService->getBalanceLogs((int) $request->userId, $page, $limit));
    }

    #[PermissionSkip]
    public function pointsLogs(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);

        return $this->paginate($this->userService->getPointsLogs((int) $request->userId, $page, $limit));
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
}
