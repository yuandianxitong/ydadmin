<?php

declare(strict_types=1);

namespace app\adminapi\controller\diy;

use app\adminapi\controller\AuthenticatedController;
use app\service\diy\DiyLinkService;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Response;
use Webman\Http\Request;

/**
 * 装修链接库。
 *
 * 端点：
 *   GET    /adminapi/diy/links            index   diy.link.list
 *   POST   /adminapi/diy/links            store   diy.link.create
 *   PUT    /adminapi/diy/links/{id}       update  diy.link.update
 *   DELETE /adminapi/diy/links/{id}       delete  diy.link.delete
 *
 * store()/update() 的校验规则由同名的 xxxRules() 无参私有方法提供。
 * index 返回裸数组，禁止 $this->paginate()。
 */
#[RouteGroup('/adminapi/diy')]
class DiyLinkController extends AuthenticatedController
{
    #[Inject]
    protected DiyLinkService $diyLinkService;

    #[Get('/links')]
    #[Permission('diy.link.list')]
    public function index(): Response
    {
        return $this->success($this->diyLinkService->list());
    }

    #[Post('/links')]
    #[Permission('diy.link.create')]
    public function store(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->storeRules());

        return $this->success($this->diyLinkService->create($data), lang('messages.create_success'));
    }

    #[Put('/links/{id:\d+}')]
    #[Permission('diy.link.update')]
    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->updateRules());
        $this->diyLinkService->update((int) $id, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Delete('/links/{id:\d+}')]
    #[Permission('diy.link.delete')]
    public function delete(Request $request, string $id): Response
    {
        $this->diyLinkService->delete((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    /**
     * @return array<string, string>
     */
    private function storeRules(): array
    {
        return [
            'label'    => 'required|max:64',
            'path'     => 'required|max:255',
            'category' => 'nullable|max:32',
            'icon'     => 'nullable|max:64',
            'sort'     => 'nullable|integer',
            'status'   => 'nullable|in:0,1',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function updateRules(): array
    {
        return [
            'label'    => 'sometimes|required|max:64',
            'path'     => 'sometimes|required|max:255',
            'category' => 'nullable|max:32',
            'icon'     => 'nullable|max:64',
            'sort'     => 'nullable|integer',
            'status'   => 'nullable|in:0,1',
        ];
    }
}
