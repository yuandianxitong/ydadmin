<?php

declare(strict_types=1);

namespace app\adminapi\controller\article;

use app\service\article\ArticleCategoryService;
use core\base\Controller;
use core\permission\Permission;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * 文章栏目（由代码生成器生成）。
 *
 * 端点（具名/静态路径必须排在 {id} 通配路由之前注册，见 config/route/article_category.php）：
 *   GET    /adminapi/article-category/list            index    article_category.list
 *   GET    /adminapi/article-category/options         options  PermissionSkip
 *   POST   /adminapi/article-category                 store    article_category.create
 *   PUT    /adminapi/article-category/{id}/status     status   article_category.update
 *   PUT    /adminapi/article-category/{id}            update   article_category.update
 *   DELETE /adminapi/article-category/{id}            delete   article_category.delete
 *
 * update 场景用 sometimes|required：字段不传时跳过（局部更新），传了空值必须校验失败。
 * 唯一性不在这里做成校验规则，由 ArticleCategoryService 查重 + 唯一索引异常兜底（spec 决策 12）。
 *
 * store()/update()/batchDelete()/status() 各自的校验规则由同名的 xxxRules() 无参私有方法
 * 提供：M2b 的 RuleReflector 按动作名反射调用 "{action}Rules"（spec §5、§14），方法必须无参、
 * 纯函数——少了任何一个动作的这层包装，文档就会静默漏掉那个端点的参数，且不会有任何报错
 * （check:context 规则七拦这个，见 scripts/check-context-discipline.sh）。
 */
class ArticleCategoryController extends Controller
{
    #[Inject]
    protected ArticleCategoryService $articleCategoryService;

    #[Permission('article_category.list')]
    public function index(Request $request): Response
    {
        $params = (array) $request->get();

        return $this->success($this->articleCategoryService->getTree($params));
    }

    #[PermissionSkip]
    public function options(Request $request): Response
    {
        return $this->success($this->articleCategoryService->getOptions((int) $request->get('exclude_id', 0)), lang('messages.get_success'));
    }

    #[Permission('article_category.list')]
    public function show(Request $request, string $id): Response
    {
        return $this->success($this->articleCategoryService->getDetail((int) $id), lang('messages.get_success'));
    }

    #[Permission('article_category.create')]
    public function store(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->storeRules(), $this->messages());

        return $this->success($this->articleCategoryService->create($data), lang('messages.create_success'));
    }

    #[Permission('article_category.update')]
    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->updateRules(), $this->messages());
        $this->articleCategoryService->update((int) $id, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Permission('article_category.delete')]
    public function delete(Request $request, string $id): Response
    {
        $this->articleCategoryService->delete((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    #[Permission('article_category.delete')]
    public function batchDelete(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->batchDeleteRules(), [
            'ids.required'  => 'article_category.article_category_ids_require',
            'ids.array'     => 'article_category.article_category_ids_require',
            'ids.min'       => 'article_category.article_category_ids_require',
            'ids.*.integer' => 'article_category.article_category_ids_integer',
        ]);
        $this->articleCategoryService->batchDelete(array_map('intval', (array) $data['ids']));

        return $this->success([], lang('messages.batch_delete_success'));
    }

    #[Permission('article_category.update')]
    public function status(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->statusRules(), [
            'status.required' => 'article_category.article_category_status_require',
            'status.integer'  => 'article_category.article_category_status_integer',
            'status.in'       => 'article_category.article_category_status_invalid',
        ]);
        $this->articleCategoryService->updateStatus((int) $id, (int) $data['status']);

        return $this->success([], lang('messages.status_update_success'));
    }

    /**
     * store 场景的字段校验规则。M2b 的 RuleReflector 按动作名反射调用 "storeRules"（spec §5、§14），
     * 这里薄包装委派给 articleCategoryRules()：规则表只在那一处维护，不重复写。
     *
     * @return array<string, string>
     */
    private function storeRules(): array
    {
        return $this->articleCategoryRules('create');
    }

    /**
     * update 场景同上，见 storeRules() 的说明。
     *
     * @return array<string, string>
     */
    private function updateRules(): array
    {
        return $this->articleCategoryRules('update');
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
     * status 端点固定校验：0/1 二值开关。
     *
     * @return array<string, string>
     */
    private function statusRules(): array
    {
        return ['status' => 'required|integer|in:0,1'];
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
    private function articleCategoryRules(string $scene): array
    {
        $required = $scene === 'create' ? 'required' : 'sometimes|required';

        return [
            'parent_id' => 'sometimes|required|integer',
            'name'      => "{$required}|string|max:100",
            'icon'      => 'sometimes|required|string|max:255',
            'sort'      => 'sometimes|required|integer|min:0',
            'status'    => 'sometimes|required|integer|in:0,1',
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
            'parent_id.required' => 'article_category.article_category_parent_id_require',
            'parent_id.integer'  => 'article_category.article_category_parent_id_integer',
            'name.required'      => 'article_category.article_category_name_require',
            'name.max'           => 'article_category.article_category_name_length',
            'icon.required'      => 'article_category.article_category_icon_require',
            'icon.max'           => 'article_category.article_category_icon_length',
            'sort.required'      => 'article_category.article_category_sort_require',
            'sort.integer'       => 'article_category.article_category_sort_integer',
            'sort.min'           => 'article_category.article_category_sort_min',
            'status.required'    => 'article_category.article_category_status_require',
            'status.integer'     => 'article_category.article_category_status_integer',
            'status.in'          => 'article_category.article_category_status_invalid',
        ];
    }
}
