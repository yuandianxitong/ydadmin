<?php

declare(strict_types=1);

namespace app\adminapi\controller\operations;

use app\adminapi\controller\AuthenticatedController;
use app\service\operations\TaskService;
use core\auth\Permission;
use core\auth\PermissionSkip;
use core\exception\BusinessException;
use core\http\Idempotent;
use DI\Attribute\Inject;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Response;
use Webman\Http\Request;
use Webman\Http\UploadFile;

#[RouteGroup('/adminapi/tasks')]
final class TaskController extends AuthenticatedController
{
    #[Inject]
    protected TaskService $tasks;

    #[Post('/import-user')]
    #[Permission('user.import')]
    #[Idempotent]
    #[\core\response\ResponseSchema(\app\resource\operations\TaskResource::class)]
    public function submitImport(Request $request): Response
    {
        $file = $request->file('file');
        if (!$file instanceof UploadFile) {
            throw new BusinessException(lang('dataimport.file_required'));
        }

        return $this->success($this->tasks->submitImport($file));
    }

    #[Post('/export')]
    #[PermissionSkip]
    #[Idempotent(permissionField: 'module', permissions: ['user' => 'user.list', 'article' => 'article.list', 'announcement' => 'announcement.list'])]
    #[\core\response\ResponseSchema(\app\resource\operations\TaskResource::class)]
    public function submitExport(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->submitExportRules());

        return $this->success($this->tasks->submitExport((string) $data['module'], isset($data['status']) ? (int) $data['status'] : null));
    }

    #[Get('/{id:[a-f0-9]+}')]
    #[PermissionSkip]
    #[\core\response\ResponseSchema(\app\resource\operations\TaskResource::class)]
    public function show(Request $request, string $id): Response
    {
        return $this->success($this->tasks->show($id));
    }

    #[Post('/{id:[a-f0-9]+}/cancel')]
    #[PermissionSkip]
    public function cancel(Request $request, string $id): Response
    {
        $this->tasks->cancel($id);

        return $this->success();
    }

    #[Get('/{id:[a-f0-9]+}/download')]
    #[PermissionSkip]
    public function download(Request $request, string $id): Response
    {
        return (new Response(200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="export.csv"', 'Cache-Control' => 'private, no-store']))
            ->withFile($this->tasks->download($id));
    }

    /** @return array<string, string> */
    private function submitExportRules(): array
    {
        return ['module' => 'required|string|in:user,article,announcement', 'status' => 'nullable|integer|in:0,1'];
    }
}
