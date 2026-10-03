<?php

declare(strict_types=1);

namespace app\adminapi\controller\wechat;

use app\adminapi\controller\AuthenticatedController;
use app\service\wechat\OfficialAccountService;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Response;
use Webman\Http\Request;

#[RouteGroup('/adminapi/wechat/official')]
final class OfficialAccountController extends AuthenticatedController
{
    #[Inject]
    protected OfficialAccountService $officialAccountService;

    #[Get('/menu')]
    #[Permission('channel.official.menu')]
    public function getMenu(Request $request): Response
    {
        return $this->success($this->officialAccountService->getMenu(), lang('messages.get_success'));
    }

    #[Post('/menu')]
    #[Permission('channel.official.menu.create')]
    public function createMenu(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->createMenuRules());
        $this->officialAccountService->createMenu($data['button']);

        return $this->success([], lang('messages.create_success'));
    }

    #[Delete('/menu')]
    #[Permission('channel.official.menu.delete')]
    public function deleteMenu(Request $request): Response
    {
        $this->officialAccountService->deleteMenu();

        return $this->success([], lang('messages.delete_success'));
    }

    /** @return array<string, string> */
    private function createMenuRules(): array
    {
        return [
            'button' => 'required|array|min:1|max:3',
        ];
    }
}
