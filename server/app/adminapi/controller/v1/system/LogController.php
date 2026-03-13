<?php
declare(strict_types=1);

namespace app\adminapi\controller\v1\system;

use app\service\system\LogService;
use core\base\Controller;
use think\Response;
use core\attribute\Permission;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '日志管理', description: '登录日志和操作日志的查看、删除、清空')]
class LogController extends Controller
{
    protected LogService $logService;

    #[Permission('system.log.login')]
    #[OA\Get(
        path: '/system/log/login',
        summary: '登录日志列表',
        security: [['bearerAuth' => []]],
        tags: ['日志管理'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', description: '页码', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', description: '每页数量', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))
        ]
    )]
    public function loginLog(): Response
    {
        $params = $this->getRequestData();
        $result = $this->logService->getLoginLogList($params);
        return $this->paginate($result);
    }

    #[Permission('system.log.operation')]
    #[OA\Get(
        path: '/system/log/operation',
        summary: '操作日志列表',
        security: [['bearerAuth' => []]],
        tags: ['日志管理'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', description: '页码', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', description: '每页数量', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))
        ]
    )]
    public function operationLog(): Response
    {
        $params = $this->getRequestData();
        $result = $this->logService->getOperationLogList($params);
        return $this->paginate($result);
    }

    #[Permission('system.log.delete')]
    #[OA\Delete(
        path: '/system/log/login/{id}',
        summary: '删除登录日志',
        security: [['bearerAuth' => []]],
        tags: ['日志管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '日志ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function deleteLoginLog(): Response
    {
        $id = (int) $this->request->param('id');
        $this->logService->deleteLoginLog($id);
        return $this->success(lang('messages.delete_success'));
    }

    #[Permission('system.log.delete')]
    #[OA\Delete(
        path: '/system/log/operation/{id}',
        summary: '删除操作日志',
        security: [['bearerAuth' => []]],
        tags: ['日志管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '日志ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function deleteOperationLog(): Response
    {
        $id = (int) $this->request->param('id');
        $this->logService->deleteOperationLog($id);
        return $this->success(lang('messages.delete_success'));
    }

    #[Permission('system.log.clear')]
    #[OA\Post(
        path: '/system/log/login/clear',
        summary: '清空登录日志',
        security: [['bearerAuth' => []]],
        tags: ['日志管理'],
        responses: [
            new OA\Response(response: 200, description: '清空成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function clearLoginLog(): Response
    {
        $this->logService->clearLoginLog();
        return $this->success(lang('messages.clear_success'));
    }

    #[Permission('system.log.clear')]
    #[OA\Post(
        path: '/system/log/operation/clear',
        summary: '清空操作日志',
        security: [['bearerAuth' => []]],
        tags: ['日志管理'],
        responses: [
            new OA\Response(response: 200, description: '清空成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function clearOperationLog(): Response
    {
        $this->logService->clearOperationLog();
        return $this->success(lang('messages.clear_success'));
    }
}
