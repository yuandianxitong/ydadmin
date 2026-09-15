<?php

declare(strict_types=1);

namespace app\adminapi\controller\catalog;

use app\service\catalog\GenCategoryService;
use core\base\Controller;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * 生成器夹具表二（无状态列/图片列/创建人列）（由代码生成器生成）。
 *
 * 端点（具名/静态路径必须排在 {id} 通配路由之前注册，见 config/route/catalog.php）：
 *   GET    /adminapi/catalog/gen-category               index        catalog.gen_category.list
 *   POST   /adminapi/catalog/gen-category/batch-delete  batchDelete  catalog.gen_category.delete
 *   GET    /adminapi/catalog/gen-category/{id}          show         catalog.gen_category.list
 *   POST   /adminapi/catalog/gen-category               store        catalog.gen_category.create
 *   PUT    /adminapi/catalog/gen-category/{id}          update       catalog.gen_category.update
 *   DELETE /adminapi/catalog/gen-category/{id}          delete       catalog.gen_category.delete
 *
 * update 场景用 sometimes|required：字段不传时跳过（局部更新），传了空值必须校验失败。
 * 唯一性不在这里做成校验规则，由 GenCategoryService 查重 + 唯一索引异常兜底（spec 决策 12）。
 *
 * genCategoryRules() 的 return 必须保持字面量形状（键是字段名、值是字符串，只有 {$required}
 * 一个插值）：M2b 的 OpenAPI 推导器要从这个私有方法里读接口参数（spec §14），改成运行时拼装
 * 就等于把这个模块从 API 文档里摘掉。
 */
class GenCategoryController extends Controller
{
    #[Inject]
    protected GenCategoryService $genCategoryService;

    #[Permission('catalog.gen_category.list')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);

        return $this->paginate($this->genCategoryService->getGenCategoryList((array) $request->get(), $page, $limit));
    }

    #[Permission('catalog.gen_category.list')]
    public function show(Request $request, string $id): Response
    {
        return $this->success($this->genCategoryService->getGenCategoryDetail((int) $id), lang('messages.get_success'));
    }

    #[Permission('catalog.gen_category.create')]
    public function store(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->genCategoryRules('create'), $this->messages());

        return $this->success($this->genCategoryService->createGenCategory($data), lang('messages.create_success'));
    }

    #[Permission('catalog.gen_category.update')]
    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->genCategoryRules('update'), $this->messages());
        $this->genCategoryService->updateGenCategory((int) $id, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Permission('catalog.gen_category.delete')]
    public function delete(Request $request, string $id): Response
    {
        $this->genCategoryService->deleteGenCategory((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    #[Permission('catalog.gen_category.delete')]
    public function batchDelete(Request $request): Response
    {
        $data = $this->validate($this->body($request), [
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ], [
            'ids.required'  => 'catalog.gen_category_ids_require',
            'ids.array'     => 'catalog.gen_category_ids_require',
            'ids.min'       => 'catalog.gen_category_ids_require',
            'ids.*.integer' => 'catalog.gen_category_ids_integer',
        ]);
        $this->genCategoryService->batchDelete(array_map('intval', (array) $data['ids']));

        return $this->success([], lang('messages.batch_delete_success'));
    }

    /**
     * 字段校验规则。create 场景必填、update 场景 sometimes|required（不传就跳过，传了空值要拒绝）。
     *
     * 这个数组必须一直是字面量：键是字段名、值是字符串，唯一允许的插值是 {$required}。
     * M2b 的 OpenAPI 推导器按这个形状读参数（spec §14）。
     *
     * @return array<string, string>
     */
    private function genCategoryRules(string $scene): array
    {
        $required = $scene === 'create' ? 'required' : 'sometimes|required';

        return [
            'name'          => "{$required}|string|max:100",
            'is_featured'   => 'sometimes|required|boolean',
            'settings'      => 'nullable|array',
            'contact_email' => 'nullable|string|max:100|email',
            'sort'          => 'sometimes|required|integer|min:0',
        ];
    }

    /**
     * message 的值即 lang key（与 ValidatorFactory::resolveMessage 的约定一致）。
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'name.required'        => 'catalog.gen_category_name_require',
            'name.max'             => 'catalog.gen_category_name_length',
            'is_featured.required' => 'catalog.gen_category_is_featured_require',
            'is_featured.boolean'  => 'catalog.gen_category_is_featured_boolean',
            'settings.array'       => 'catalog.gen_category_settings_array',
            'contact_email.max'    => 'catalog.gen_category_contact_email_length',
            'contact_email.email'  => 'catalog.gen_category_contact_email_email',
            'sort.required'        => 'catalog.gen_category_sort_require',
            'sort.integer'         => 'catalog.gen_category_sort_integer',
            'sort.min'             => 'catalog.gen_category_sort_min',
        ];
    }
}
