<?php

// 由代码生成器生成，`php start.php reload` 后生效。

declare(strict_types=1);

namespace app\adminapi\controller\demo;

use app\adminapi\controller\AuthenticatedController;
use app\service\demo\GenArticleService;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Response;
use Webman\Http\Request;

/**
 * 生成器夹具表（由代码生成器生成）。
 *
 * 端点：
 *   GET    /adminapi/demo/gen-article                  index        demo.gen_article.list
 *   POST   /adminapi/demo/gen-article/batch-delete     batchDelete  demo.gen_article.delete
 *   GET    /adminapi/demo/gen-article/{id:\d+}         show         demo.gen_article.list
 *   POST   /adminapi/demo/gen-article                  store        demo.gen_article.create
 *   PUT    /adminapi/demo/gen-article/{id:\d+}         update       demo.gen_article.update
 *   DELETE /adminapi/demo/gen-article/{id:\d+}         delete       demo.gen_article.delete
 *   PUT    /adminapi/demo/gen-article/{id:\d+}/status  status       demo.gen_article.status
 *
 * update 场景用 sometimes|required：字段不传时跳过（局部更新），传了空值必须校验失败。
 * 唯一性不在这里做成校验规则，由 GenArticleService 查重 + 唯一索引异常兜底（spec 决策 12）。
 *
 * store()/update()/batchDelete()/status() 各自的校验规则由同名的 xxxRules() 无参私有方法
 * 提供：M2b 的 RuleReflector 按动作名反射调用 "{action}Rules"（spec §5、§14），方法必须无参、
 * 纯函数——少了任何一个动作的这层包装，文档就会静默漏掉那个端点的参数，且不会有任何报错
 * （check:context 规则七拦这个，见 scripts/check-context-discipline.sh）。
 */
#[RouteGroup('/adminapi/demo/gen-article')]
class GenArticleController extends AuthenticatedController
{
    #[Inject]
    protected GenArticleService $genArticleService;

    #[Get('')]
    #[Permission('demo.gen_article.list')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);

        return $this->paginate($this->genArticleService->getGenArticleList((array) $request->get(), $page, $limit));
    }

    #[Get('/{id:\d+}')]
    #[Permission('demo.gen_article.list')]
    public function show(Request $request, string $id): Response
    {
        return $this->success($this->genArticleService->getGenArticleDetail((int) $id), lang('messages.get_success'));
    }

    #[Post('')]
    #[Permission('demo.gen_article.create')]
    public function store(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->storeRules(), $this->messages());

        return $this->success($this->genArticleService->createGenArticle($data), lang('messages.create_success'));
    }

    #[Put('/{id:\d+}')]
    #[Permission('demo.gen_article.update')]
    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->updateRules(), $this->messages());
        $this->genArticleService->updateGenArticle((int) $id, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Delete('/{id:\d+}')]
    #[Permission('demo.gen_article.delete')]
    public function delete(Request $request, string $id): Response
    {
        $this->genArticleService->deleteGenArticle((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    #[Post('/batch-delete')]
    #[Permission('demo.gen_article.delete')]
    public function batchDelete(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->batchDeleteRules(), [
            'ids.required'  => 'demo.gen_article_ids_require',
            'ids.array'     => 'demo.gen_article_ids_require',
            'ids.min'       => 'demo.gen_article_ids_require',
            'ids.*.integer' => 'demo.gen_article_ids_integer',
        ]);
        $this->genArticleService->batchDelete(array_map('intval', (array) $data['ids']));

        return $this->success([], lang('messages.batch_delete_success'));
    }

    #[Put('/{id:\d+}/status')]
    #[Permission('demo.gen_article.status')]
    public function status(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->statusRules(), [
            'status.required' => 'demo.gen_article_status_require',
            'status.integer'  => 'demo.gen_article_status_integer',
            'status.in'       => 'demo.gen_article_status_invalid',
        ]);
        $this->genArticleService->updateStatus((int) $id, (int) $data['status']);

        return $this->success([], lang('messages.status_update_success'));
    }

    /**
     * store 场景的字段校验规则。M2b 的 RuleReflector 按动作名反射调用 "storeRules"（spec §5、§14），
     * 这里薄包装委派给 genArticleRules()：规则表只在那一处维护，不重复写。
     *
     * @return array<string, string>
     */
    private function storeRules(): array
    {
        return $this->genArticleRules('create');
    }

    /**
     * update 场景同上，见 storeRules() 的说明。
     *
     * @return array<string, string>
     */
    private function updateRules(): array
    {
        return $this->genArticleRules('update');
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
    private function genArticleRules(string $scene): array
    {
        $required = $scene === 'create' ? 'required' : 'sometimes|required';

        return [
            'title'        => "{$required}|string|max:200",
            'summary'      => 'nullable|string|max:500',
            'content'      => 'nullable|string',
            'cover_image'  => 'nullable|string|max:255',
            'category'     => 'sometimes|required|in:news,tech,life',
            'price'        => 'sometimes|required|numeric',
            'view_count'   => 'sometimes|required|integer',
            'slug'         => "{$required}|string|max:100",
            'published_at' => 'nullable|date',
            'status'       => 'sometimes|required|integer|in:0,1',
            'sort'         => 'sometimes|required|integer|min:0',
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
            'title.required'      => 'demo.gen_article_title_require',
            'title.max'           => 'demo.gen_article_title_length',
            'summary.max'         => 'demo.gen_article_summary_length',
            'cover_image.max'     => 'demo.gen_article_cover_image_length',
            'category.required'   => 'demo.gen_article_category_require',
            'category.in'         => 'demo.gen_article_category_invalid',
            'price.required'      => 'demo.gen_article_price_require',
            'price.numeric'       => 'demo.gen_article_price_numeric',
            'view_count.required' => 'demo.gen_article_view_count_require',
            'view_count.integer'  => 'demo.gen_article_view_count_integer',
            'slug.required'       => 'demo.gen_article_slug_require',
            'slug.max'            => 'demo.gen_article_slug_length',
            'published_at.date'   => 'demo.gen_article_published_at_date',
            'status.required'     => 'demo.gen_article_status_require',
            'status.integer'      => 'demo.gen_article_status_integer',
            'status.in'           => 'demo.gen_article_status_invalid',
            'sort.required'       => 'demo.gen_article_sort_require',
            'sort.integer'        => 'demo.gen_article_sort_integer',
            'sort.min'            => 'demo.gen_article_sort_min',
        ];
    }
}
