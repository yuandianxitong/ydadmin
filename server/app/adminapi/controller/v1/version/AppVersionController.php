<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\controller\v1\version;

use core\base\Controller;
use core\attribute\Permission;
use app\service\version\AppVersionService;
use app\adminapi\validate\v1\version\AppVersionValidate;
use think\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '应用版本', description: '应用版本的增删改查管理')]
class AppVersionController extends Controller
{
    protected AppVersionService $appVersionService;

    /**
     * 版本列表
     */
    #[Permission('version.list')]
    #[OA\Get(
        path: '/version/list',
        summary: '获取版本列表',
        security: [['bearerAuth' => []]],
        tags: ['应用版本'],
        parameters: [
            new OA\Parameter(name: 'page_no', in: 'query', description: '页码', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'page_size', in: 'query', description: '每页数量', schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'platform', in: 'query', description: '平台', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', description: '状态', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))
        ]
    )]
    public function list(): Response
    {
        $params = $this->getRequestData([
            'page_no'   => 1,
            'page_size' => 20,
            'platform'  => '',
            'status'    => '',
        ]);
        $result = $this->appVersionService->getList($params);
        return $this->paginate($result);
    }

    /**
     * 版本详情
     */
    #[Permission('version.detail')]
    #[OA\Get(
        path: '/version/detail/{id}',
        summary: '获取版本详情',
        security: [['bearerAuth' => []]],
        tags: ['应用版本'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '版本ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function detail(): Response
    {
        $id = (int) $this->request->param('id');
        $result = $this->appVersionService->detail($id);
        if (!$result) {
            return $this->error(lang('business.record_not_found'));
        }
        return $this->success(lang('messages.get_success'), $result);
    }

    /**
     * 创建版本
     */
    #[Permission('version.create')]
    #[OA\Post(
        path: '/version',
        summary: '创建版本',
        security: [['bearerAuth' => []]],
        tags: ['应用版本'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['platform', 'version', 'version_code'],
                properties: [
                    new OA\Property(property: 'platform', type: 'string', description: '平台(android/ios)'),
                    new OA\Property(property: 'version', type: 'string', description: '版本号'),
                    new OA\Property(property: 'version_code', type: 'integer', description: '版本编码'),
                    new OA\Property(property: 'download_url', type: 'string', description: '下载地址'),
                    new OA\Property(property: 'description', type: 'string', description: '版本描述'),
                    new OA\Property(property: 'force_update', type: 'integer', description: '强制更新(0否 1是)', enum: [0, 1]),
                    new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '创建成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function create(): Response
    {
        $data = $this->request->only([
            'platform', 'version', 'version_code',
            'download_url', 'description', 'force_update', 'status',
        ]);
        $this->validate($data, AppVersionValidate::class, [], false, 'create');
        $result = $this->appVersionService->create($data);
        return $this->success(lang('messages.create_success'), $result);
    }

    /**
     * 更新版本
     */
    #[Permission('version.update')]
    #[OA\Put(
        path: '/version/{id}',
        summary: '更新版本',
        security: [['bearerAuth' => []]],
        tags: ['应用版本'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '版本ID', schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'platform', type: 'string', description: '平台(android/ios)'),
                new OA\Property(property: 'version', type: 'string', description: '版本号'),
                new OA\Property(property: 'version_code', type: 'integer', description: '版本编码'),
                new OA\Property(property: 'download_url', type: 'string', description: '下载地址'),
                new OA\Property(property: 'description', type: 'string', description: '版本描述'),
                new OA\Property(property: 'force_update', type: 'integer', description: '强制更新(0否 1是)', enum: [0, 1]),
                new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: '更新成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function update(): Response
    {
        $id = (int) $this->request->param('id');
        $data = $this->request->only([
            'platform', 'version', 'version_code',
            'download_url', 'description', 'force_update', 'status',
        ]);
        $this->validate($data, AppVersionValidate::class, [], false, 'update');
        $this->appVersionService->update($id, $data);
        return $this->success(lang('messages.update_success'));
    }

    /**
     * 删除版本
     */
    #[Permission('version.delete')]
    #[OA\Delete(
        path: '/version/{id}',
        summary: '删除版本',
        security: [['bearerAuth' => []]],
        tags: ['应用版本'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '版本ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function delete(): Response
    {
        $id = (int) $this->request->param('id');
        $this->appVersionService->delete($id);
        return $this->success(lang('messages.delete_success'));
    }
}
