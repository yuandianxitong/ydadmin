<?php

declare(strict_types=1);

namespace app\adminapi\controller\feedback;

use app\adminapi\controller\AuthenticatedController;
use app\service\feedback\FeedbackService;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Response;
use Webman\Http\Request;

/**
 * 管理端反馈（M7a spec §5）。无创建路由：反馈只由 C 端提交。
 *
 * 端点（静态路径由 FastRoute 先匹配，不靠方法声明顺序）：
 *   GET    /adminapi/feedback/list          index   feedback.list
 *   GET    /adminapi/feedback/detail/{id}   show    feedback.list
 *   POST   /adminapi/feedback/reply         reply   feedback.reply
 *   POST   /adminapi/feedback/close/{id}    close   feedback.close
 *   DELETE /adminapi/feedback/{id}          delete  feedback.delete
 *
 * reply() 的校验规则由 replyRules() 提供：M2b 的 RuleReflector 按动作名反射调用 "{action}Rules"。
 */
#[RouteGroup('/adminapi/feedback')]
class FeedbackController extends AuthenticatedController
{
    #[Inject]
    protected FeedbackService $feedbackService;

    #[Get('/list')]
    #[Permission('feedback.list')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request, 20);

        return $this->paginate($this->feedbackService->getList((array) $request->get(), $page, $limit));
    }

    #[Get('/detail/{id:\d+}')]
    #[Permission('feedback.list')]
    public function show(Request $request, string $id): Response
    {
        return $this->success($this->feedbackService->getDetail((int) $id), lang('messages.get_success'));
    }

    #[Post('/reply')]
    #[Permission('feedback.reply')]
    public function reply(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->replyRules(), $this->replyMessages());
        $this->feedbackService->reply((int) $data['id'], (string) $data['reply']);

        return $this->success([], lang('messages.success'));
    }

    #[Post('/close/{id:\d+}')]
    #[Permission('feedback.close')]
    public function close(Request $request, string $id): Response
    {
        $this->feedbackService->close((int) $id);

        return $this->success([], lang('messages.success'));
    }

    #[Delete('/{id:\d+}')]
    #[Permission('feedback.delete')]
    public function delete(Request $request, string $id): Response
    {
        $this->feedbackService->delete((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    /**
     * @return array<string, string>
     */
    private function replyRules(): array
    {
        return [
            'id'    => 'required|integer|min:1',
            'reply' => 'required|string|min:1|max:2000',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function replyMessages(): array
    {
        return [
            'id.required'    => 'feedback.id_require',
            'id.integer'     => 'feedback.id_require',
            'id.min'         => 'feedback.id_require',
            'reply.required' => 'feedback.reply_require',
            'reply.string'   => 'feedback.reply_require',
            'reply.min'      => 'feedback.reply_require',
            'reply.max'      => 'feedback.reply_length',
        ];
    }
}
