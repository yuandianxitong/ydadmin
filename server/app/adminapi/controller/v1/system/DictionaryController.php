<?php
declare(strict_types=1);

namespace app\adminapi\controller\v1\system;

use app\service\system\DictionaryService;
use app\adminapi\validate\v1\system\DictionaryValidate;
use app\adminapi\validate\v1\system\DictionaryItemValidate;
use core\base\Controller;
use think\Response;
use core\attribute\Permission;
use core\attribute\PermissionSkip;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '数据字典', description: '字典及字典项的管理')]
class DictionaryController extends Controller
{
    protected DictionaryService $dictionaryService;

    // ========== 字典管理 ==========

    #[Permission('system.dictionary.list')]
    #[OA\Get(
        path: '/system/dictionary',
        summary: '字典列表',
        security: [['bearerAuth' => []]],
        tags: ['数据字典'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', description: '页码', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', description: '每页数量', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))
        ]
    )]
    public function index(): Response
    {
        $params = $this->getRequestData();
        $result = $this->dictionaryService->getDictionaryList($params);

        return $this->paginate($result);
    }

    #[Permission('system.dictionary.list')]
    #[OA\Get(
        path: '/system/dictionary/{id}',
        summary: '字典详情',
        security: [['bearerAuth' => []]],
        tags: ['数据字典'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '字典ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function show(): Response
    {
        $id = (int) $this->request->param('id');
        $result = $this->dictionaryService->getDictionaryDetail($id);

        return $this->success(lang('messages.get_success'), $result);
    }

    #[Permission('system.dictionary.create')]
    #[OA\Post(
        path: '/system/dictionary',
        summary: '创建字典',
        security: [['bearerAuth' => []]],
        tags: ['数据字典'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'code'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', description: '字典名称'),
                    new OA\Property(property: 'code', type: 'string', description: '字典编码'),
                    new OA\Property(property: 'description', type: 'string', description: '描述'),
                    new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '创建成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '验证失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    public function store(): Response
    {
        $data = $this->request->post();
        $this->validate($data, DictionaryValidate::class, [], 'create');

        $result = $this->dictionaryService->createDictionary($data);

        return $this->success(lang('messages.create_success'), $result);
    }

    #[Permission('system.dictionary.update')]
    #[OA\Put(
        path: '/system/dictionary/{id}',
        summary: '更新字典',
        security: [['bearerAuth' => []]],
        tags: ['数据字典'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '字典ID', schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'name', type: 'string', description: '字典名称'),
                new OA\Property(property: 'code', type: 'string', description: '字典编码'),
                new OA\Property(property: 'description', type: 'string', description: '描述'),
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
        $data = $this->request->post();
        $this->validate($data, DictionaryValidate::class, [], 'update');

        $this->dictionaryService->updateDictionary($id, $data);

        return $this->success(lang('messages.update_success'));
    }

    #[Permission('system.dictionary.delete')]
    #[OA\Delete(
        path: '/system/dictionary/{id}',
        summary: '删除字典',
        security: [['bearerAuth' => []]],
        tags: ['数据字典'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '字典ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function delete(): Response
    {
        $id = (int) $this->request->param('id');
        $this->dictionaryService->deleteDictionary($id);

        return $this->success(lang('messages.delete_success'));
    }

    #[Permission('system.dictionary.delete')]
    #[OA\Post(
        path: '/system/dictionary/batch-delete',
        summary: '批量删除字典',
        security: [['bearerAuth' => []]],
        tags: ['数据字典'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['ids'],
                properties: [
                    new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer'), description: '字典ID列表'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '批量删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function batchDelete(): Response
    {
        $ids = $this->request->post('ids', []);

        if (empty($ids)) {
            return $this->error(lang('business.please_select_dict'));
        }

        foreach ($ids as $id) {
            $this->dictionaryService->deleteDictionary((int)$id);
        }

        return $this->success(lang('messages.batch_delete_success'));
    }

    #[PermissionSkip]
    #[OA\Get(
        path: '/system/dictionary/options',
        summary: '根据code获取字典选项（供前端下拉使用）',
        security: [['bearerAuth' => []]],
        tags: ['数据字典'],
        parameters: [
            new OA\Parameter(name: 'code', in: 'query', required: true, description: '字典编码', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '请求失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    public function options(): Response
    {
        $code = $this->request->param('code', '');
        if (empty($code)) {
            return $this->error(lang('business.dict_code_required'));
        }

        $result = $this->dictionaryService->getOptionsByCode($code);

        return $this->success(lang('messages.get_success'), $result);
    }

    #[PermissionSkip]
    #[OA\Get(
        path: '/system/dictionary/batch-options',
        summary: '批量获取字典选项',
        security: [['bearerAuth' => []]],
        tags: ['数据字典'],
        parameters: [
            new OA\Parameter(name: 'codes', in: 'query', required: true, description: '字典编码列表（数组或逗号分隔）', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '请求失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    public function batchOptions(): Response
    {
        $codes = $this->request->param('codes', []);
        if (empty($codes)) {
            return $this->error(lang('business.dict_code_required'));
        }

        if (is_string($codes)) {
            $codes = explode(',', $codes);
        }

        $result = $this->dictionaryService->getOptionsByCodes($codes);

        return $this->success(lang('messages.get_success'), $result);
    }

    // ========== 字典项管理 ==========

    #[Permission('system.dictionary.list')]
    #[OA\Get(
        path: '/system/dictionary/{id}/items',
        summary: '字典项列表',
        security: [['bearerAuth' => []]],
        tags: ['数据字典'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '字典ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function items(): Response
    {
        $dictionaryId = (int) $this->request->param('id');
        $result = $this->dictionaryService->getItemList($dictionaryId);

        return $this->success(lang('messages.get_success'), $result);
    }

    #[Permission('system.dictionary.create')]
    #[OA\Post(
        path: '/system/dictionary/item',
        summary: '创建字典项',
        security: [['bearerAuth' => []]],
        tags: ['数据字典'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['dictionary_id', 'label', 'value'],
                properties: [
                    new OA\Property(property: 'dictionary_id', type: 'integer', description: '字典ID'),
                    new OA\Property(property: 'label', type: 'string', description: '标签'),
                    new OA\Property(property: 'value', type: 'string', description: '值'),
                    new OA\Property(property: 'sort', type: 'integer', description: '排序'),
                    new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '创建成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '验证失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    public function storeItem(): Response
    {
        $data = $this->request->post();
        $this->validate($data, DictionaryItemValidate::class, [], 'create');

        $result = $this->dictionaryService->createItem($data);

        return $this->success(lang('messages.create_success'), $result);
    }

    #[Permission('system.dictionary.update')]
    #[OA\Put(
        path: '/system/dictionary/item/{id}',
        summary: '更新字典项',
        security: [['bearerAuth' => []]],
        tags: ['数据字典'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '字典项ID', schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'label', type: 'string', description: '标签'),
                new OA\Property(property: 'value', type: 'string', description: '值'),
                new OA\Property(property: 'sort', type: 'integer', description: '排序'),
                new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: '更新成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function updateItem(): Response
    {
        $id = (int) $this->request->param('id');
        $data = $this->request->post();
        $this->validate($data, DictionaryItemValidate::class, [], 'update');

        $this->dictionaryService->updateItem($id, $data);

        return $this->success(lang('messages.update_success'));
    }

    #[Permission('system.dictionary.delete')]
    #[OA\Delete(
        path: '/system/dictionary/item/{id}',
        summary: '删除字典项',
        security: [['bearerAuth' => []]],
        tags: ['数据字典'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '字典项ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function deleteItem(): Response
    {
        $id = (int) $this->request->param('id');
        $this->dictionaryService->deleteItem($id);

        return $this->success(lang('messages.delete_success'));
    }
}
