<?php

declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\service\system\FileService;
use core\base\Controller;
use core\permission\Permission;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * 文件管理（契约 §2.9.1）。
 *
 * 端点（具名路由在 {id} 通配路由之前注册）：
 *   GET    /adminapi/system/file                index        system.file.list
 *   GET    /adminapi/system/file/groups          groups       PermissionSkip
 *   POST   /adminapi/system/file/move-group      moveGroup    system.file.update
 *   POST   /adminapi/system/file/batch-delete    batchDelete  system.file.delete
 *   PUT    /adminapi/system/file/{id}/rename     rename       system.file.update
 *   DELETE /adminapi/system/file/{id}            delete       system.file.delete
 *
 * groups 是 PermissionSkip：素材选择器在没有 system.file.list 的页面里也要能列分组。
 * mime_type 是前端左侧「文件类型」的 6 个桶名，值域在这里锁死，翻译成查询条件由仓储完成。
 */
class FileController extends Controller
{
    #[Inject]
    protected FileService $fileService;

    #[Permission('system.file.list')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request, 20);
        $params = $this->validate((array) $request->get(), [
            'keyword'   => 'nullable|string|max:100',
            'group'     => 'nullable|string|max:100',
            'mime_type' => 'nullable|in:image,video,audio,document,archive,other',
        ], [
            'keyword.max'  => 'validation.file_keyword_max',
            'group.max'    => 'validation.file_group_max',
            'mime_type.in' => 'validation.file_mime_type_invalid',
        ]);

        return $this->paginate($this->fileService->getFileList($params, $page, $limit));
    }

    #[PermissionSkip]
    public function groups(): Response
    {
        return $this->success($this->fileService->getGroups(), lang('messages.get_success'));
    }

    #[Permission('system.file.update')]
    public function moveGroup(Request $request): Response
    {
        $data = $this->validate($this->body($request), [
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
            'group' => 'required|string|max:100',
        ], [
            'ids.required'   => 'validation.file_ids_require',
            'ids.array'      => 'validation.file_ids_require',
            'ids.min'        => 'validation.file_ids_require',
            'ids.*.integer'  => 'validation.file_ids_integer',
            'group.required' => 'validation.file_group_require',
            'group.string'   => 'validation.file_group_require',
            'group.max'      => 'validation.file_group_max',
        ]);
        $this->fileService->moveToGroup(array_map('intval', (array) $data['ids']), (string) $data['group']);

        return $this->success([], lang('messages.move_success'));
    }

    #[Permission('system.file.update')]
    public function rename(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), [
            'name' => 'required|string|max:255',
        ], [
            'name.required' => 'validation.file_name_require',
            'name.string'   => 'validation.file_name_require',
            'name.max'      => 'validation.file_name_max',
        ]);
        $this->fileService->renameFile((int) $id, (string) $data['name']);

        return $this->success([], lang('messages.rename_success'));
    }

    #[Permission('system.file.delete')]
    public function delete(Request $request, string $id): Response
    {
        $this->fileService->deleteFile((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    #[Permission('system.file.delete')]
    public function batchDelete(Request $request): Response
    {
        $data = $this->validate($this->body($request), [
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ], [
            'ids.required'  => 'validation.file_ids_require',
            'ids.array'     => 'validation.file_ids_require',
            'ids.min'       => 'validation.file_ids_require',
            'ids.*.integer' => 'validation.file_ids_integer',
        ]);
        $count = $this->fileService->batchDelete(array_map('intval', (array) $data['ids']));

        return $this->success([], sprintf(lang('messages.file_delete_count'), $count));
    }
}
