<?php

declare(strict_types=1);

namespace app\api\controller\feedback;

use app\api\controller\AuthenticatedController;
use app\service\feedback\FeedbackService;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Response;
use Webman\Http\Request;

/**
 * C 端意见反馈（M7a spec §5）。全部端点挂 ApiAuthMiddleware，身份取自 $request->userId；
 * C 端没有权限体系，方法一律 #[PermissionSkip]。
 *
 *   POST /api/feedback/submit          submit  提交；user_id / status / reply / replied_by 不接收
 *   GET  /api/feedback/list            list    分页（page_no/page_size，默认 10），只含本人
 *   GET  /api/feedback/detail/{id}     detail  本人详情；他人 / 不存在 → body.code 404
 */
#[RouteGroup('/api/feedback')]
class FeedbackController extends AuthenticatedController
{
    #[Inject]
    protected FeedbackService $feedbackService;

    #[Post('/submit')]
    #[PermissionSkip]
    public function submit(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->submitRules(), $this->submitMessages());

        return $this->success($this->feedbackService->submit((int) $request->userId, $data), lang('messages.create_success'));
    }

    #[Get('/list')]
    #[PermissionSkip]
    public function list(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request, 10);

        return $this->paginate($this->feedbackService->getUserList((int) $request->userId, $page, $limit));
    }

    #[Get('/detail/{id:\d+}')]
    #[PermissionSkip]
    public function detail(Request $request, string $id): Response
    {
        return $this->success($this->feedbackService->getUserDetail((int) $request->userId, (int) $id), lang('messages.get_success'));
    }

    /**
     * @return array<string, string>
     */
    private function submitRules(): array
    {
        return [
            'content'   => 'required|string|max:2000',
            'type'      => 'nullable|string|in:suggestion,bug,complaint,other',
            'images'    => 'nullable|array|max:9',
            'images.*'  => 'string|max:500',
            'contact'   => 'nullable|string|max:100',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function submitMessages(): array
    {
        return [
            'content.required' => 'feedback.content_require',
            'content.max'      => 'feedback.content_max',
            'images.max'       => 'feedback.images_max',
            'type.in'          => 'feedback.type_invalid',
            'images.array'     => 'feedback.images_array',
            'contact.max'      => 'feedback.contact_max',
        ];
    }
}
