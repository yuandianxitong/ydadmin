<?php

declare(strict_types=1);

namespace app\adminapi\controller\wechat;

use app\service\wechat\OfficialAccountService;
use core\base\Controller;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

final class OfficialAccountController extends Controller
{
    #[Inject]
    protected OfficialAccountService $officialAccountService;

    #[Permission('channel.official.menu')]
    public function getMenu(Request $request): Response
    {
        return $this->success($this->officialAccountService->getMenu(), lang('messages.get_success'));
    }

    #[Permission('channel.official.menu.create')]
    public function createMenu(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->createMenuRules());
        $this->officialAccountService->createMenu($data['button']);

        return $this->success([], lang('messages.create_success'));
    }

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
