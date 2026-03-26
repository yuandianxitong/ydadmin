<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\controller\v1\region;

use core\base\Controller;
use core\attribute\Permission;
use app\service\region\RegionService;
use app\adminapi\validate\v1\region\RegionValidate;
use think\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '区域管理', description: '区域的增删改查、树形结构管理')]
class RegionController extends Controller
{
    protected RegionService $regionService;

    /**
     * 地区列表
     */
    #[Permission('region.list')]
    #[OA\Get(
        path: '/region/list',
        summary: '获取地区列表',
        security: [['bearerAuth' => []]],
        tags: ['区域管理'],
        parameters: [
            new OA\Parameter(name: 'page_no', in: 'query', description: '页码', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'page_size', in: 'query', description: '每页数量', schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'parent_id', in: 'query', description: '父级ID', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'level', in: 'query', description: '层级', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'keyword', in: 'query', description: '关键词搜索', schema: new OA\Schema(type: 'string')),
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
            'parent_id' => '',
            'level'     => '',
            'keyword'   => '',
        ]);
        $result = $this->regionService->getList($params);
        return $this->paginate($result);
    }

    /**
     * 地区树（级联选择器）
     */
    #[OA\Get(
        path: '/region/tree',
        summary: '获取地区树形结构',
        security: [['bearerAuth' => []]],
        tags: ['区域管理'],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function tree(): Response
    {
        $result = $this->regionService->getTree();
        return $this->success(lang('messages.get_success'), $result);
    }

    /**
     * 地区详情
     */
    #[Permission('region.list')]
    #[OA\Get(
        path: '/region/detail/{id}',
        summary: '获取地区详情',
        security: [['bearerAuth' => []]],
        tags: ['区域管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '地区ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function detail(): Response
    {
        $id = (int) $this->request->param('id');
        $result = $this->regionService->detail($id);
        if (!$result) {
            return $this->error(lang('business.record_not_found'));
        }
        return $this->success(lang('messages.get_success'), $result);
    }

    /**
     * 创建地区
     */
    #[Permission('region.create')]
    #[OA\Post(
        path: '/region',
        summary: '创建地区',
        security: [['bearerAuth' => []]],
        tags: ['区域管理'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'code'],
                properties: [
                    new OA\Property(property: 'parent_id', type: 'integer', description: '父级ID'),
                    new OA\Property(property: 'name', type: 'string', description: '地区名称'),
                    new OA\Property(property: 'code', type: 'string', description: '地区编码'),
                    new OA\Property(property: 'level', type: 'integer', description: '层级'),
                    new OA\Property(property: 'sort', type: 'integer', description: '排序'),
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
        $data = $this->request->only(['parent_id', 'name', 'code', 'level', 'sort', 'status']);
        $this->validate($data, RegionValidate::class, [], false, 'create');
        $result = $this->regionService->create($data);
        return $this->success(lang('messages.create_success'), $result);
    }

    /**
     * 更新地区
     */
    #[Permission('region.update')]
    #[OA\Put(
        path: '/region/{id}',
        summary: '更新地区',
        security: [['bearerAuth' => []]],
        tags: ['区域管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '地区ID', schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'parent_id', type: 'integer', description: '父级ID'),
                new OA\Property(property: 'name', type: 'string', description: '地区名称'),
                new OA\Property(property: 'code', type: 'string', description: '地区编码'),
                new OA\Property(property: 'level', type: 'integer', description: '层级'),
                new OA\Property(property: 'sort', type: 'integer', description: '排序'),
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
        $data = $this->request->only(['parent_id', 'name', 'code', 'level', 'sort', 'status']);
        $this->validate($data, RegionValidate::class, [], false, 'update');
        $this->regionService->update($id, $data);
        return $this->success(lang('messages.update_success'));
    }

    /**
     * 删除地区
     */
    #[Permission('region.delete')]
    #[OA\Delete(
        path: '/region/{id}',
        summary: '删除地区',
        security: [['bearerAuth' => []]],
        tags: ['区域管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '地区ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function delete(): Response
    {
        $id = (int) $this->request->param('id');
        $this->regionService->delete($id);
        return $this->success(lang('messages.delete_success'));
    }
}
