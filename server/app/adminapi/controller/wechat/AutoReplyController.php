<?php

declare(strict_types=1);

namespace app\adminapi\controller\wechat;

use app\adminapi\controller\AuthenticatedController;
use app\service\wechat\AutoReplyService;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Response;
use Webman\Http\Request;

#[RouteGroup('/adminapi/wechat/auto-reply')]
class AutoReplyController extends AuthenticatedController
{
    #[Inject]
    protected AutoReplyService $autoReplyService;

    #[Get('')]
    #[Permission('channel.official.auto_reply')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);
        $params = $this->validate((array) $request->get(), $this->indexRules());

        return $this->paginate($this->autoReplyService->getList($params, $page, $limit));
    }

    #[Get('/{id:\d+}')]
    #[Permission('channel.official.auto_reply')]
    public function show(Request $request, string $id): Response
    {
        return $this->success($this->autoReplyService->getDetail((int) $id), lang('messages.get_success'));
    }

    #[Post('')]
    #[Permission('channel.official.auto_reply.create')]
    public function store(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->storeRules());
        $this->autoReplyService->create($data);

        return $this->success([], lang('messages.create_success'));
    }

    #[Put('/{id:\d+}')]
    #[Permission('channel.official.auto_reply.update')]
    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->updateRules());
        $this->autoReplyService->update((int) $id, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Delete('/{id:\d+}')]
    #[Permission('channel.official.auto_reply.delete')]
    public function delete(Request $request, string $id): Response
    {
        $this->autoReplyService->delete((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    /** @return array<string, string> */
    private function indexRules(): array
    {
        return [
            'type' => 'nullable|in:keyword,subscribe,default',
        ];
    }

    /** @return array<string, string> */
    private function storeRules(): array
    {
        return [
            'type'       => 'required|in:keyword,subscribe,default',
            'keyword'    => 'required_if:type,keyword|nullable|string|max:200',
            'match_type' => 'nullable|in:exact,fuzzy',
            'content'    => 'required|string|max:2000',
            'sort_order' => 'nullable|integer|min:0',
            'status'     => 'nullable|in:0,1',
        ];
    }

    /** @return array<string, string> */
    private function updateRules(): array
    {
        return [
            'type'       => 'sometimes|required|in:keyword,subscribe,default',
            'keyword'    => 'sometimes|required_if:type,keyword|nullable|string|max:200',
            'match_type' => 'sometimes|nullable|in:exact,fuzzy',
            'content'    => 'sometimes|required|string|max:2000',
            'sort_order' => 'sometimes|nullable|integer|min:0',
            'status'     => 'sometimes|nullable|in:0,1',
        ];
    }
}
