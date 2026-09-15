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
 * store()/update()/batchDelete()/status() 各自的校验规则由同名的 xxxRules() 无参私有方法
 * 提供：M2b 的 RuleReflector 按动作名反射调用 "{action}Rules"（spec §5、§14），方法必须无参、
 * 纯函数——少了任何一个动作的这层包装，文档就会静默漏掉那个端点的参数，且不会有任何报错
 * （check:context 规则七拦这个，见 scripts/check-context-discipline.sh）。
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
        $data = $this->validate($this->body($request), $this->storeRules(), $this->messages());

        return $this->success($this->genCategoryService->createGenCategory($data), lang('messages.create_success'));
    }

    #[Permission('catalog.gen_category.update')]
    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->updateRules(), $this->messages());
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
        $data = $this->validate($this->body($request), $this->batchDeleteRules(), [
            'ids.required'  => 'catalog.gen_category_ids_require',
            'ids.array'     => 'catalog.gen_category_ids_require',
            'ids.min'       => 'catalog.gen_category_ids_require',
            'ids.*.integer' => 'catalog.gen_category_ids_integer',
        ]);
        $this->genCategoryService->batchDelete(array_map('intval', (array) $data['ids']));

        return $this->success([], lang('messages.batch_delete_success'));
    }

    /**
     * store 场景的字段校验规则。M2b 的 RuleReflector 按动作名反射调用 "storeRules"（spec §5、§14），
     * 这里薄包装委派给 genCategoryRules()：规则表只在那一处维护，不重复写。
     *
     * @return array<string, string>
     */
    private function storeRules(): array
    {
        return $this->genCategoryRules('create');
    }

    /**
     * update 场景同上，见 storeRules() 的说明。
     *
     * @return array<string, string>
     */
    private function updateRules(): array
    {
        return $this->genCategoryRules('update');
    }

    /**
     * 批量删除固定校验：ids 必须是非空数组，元素必须是整数（CLAUDE.md + spec §6.1 第 4 条）。
     *
     * @return array<string, string>
     */
    private function batchDeleteRules(): array
    {
        return [
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ];
    }

    /**
     * store/update 共用的字段校验规则表，由 storeRules()/updateRules() 按场景委派调用（不再被
     * store()/update() 直接调用）。create 场景必填、update 场景 sometimes|required（不传就跳过，
     * 传了空值要拒绝）。
     *
     * 这个数组必须一直是字面量：键是字段名、值是字符串，唯一允许的插值是 {$required}。这不是
     * 给 M2b 反射用的约束（反射直接执行 storeRules()/updateRules() 拿完全求值后的返回值，不管
     * 内部怎么实现）——而是给人读的：这张表本身就是接口契约，写成运行时拼装（foreach 塞、
     * array_merge、变量当键）会让人没法一眼看出这个模块收哪些字段。
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
