<?php

declare(strict_types=1);

namespace app\adminapi\controller\demo;

use app\service\demo\GenArticleService;
use core\base\Controller;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * 生成器夹具表（由代码生成器生成）。
 *
 * 端点（具名/静态路径必须排在 {id} 通配路由之前注册，见 config/route/demo.php）：
 *   GET    /adminapi/demo/gen-article               index        demo.gen_article.list
 *   POST   /adminapi/demo/gen-article/batch-delete  batchDelete  demo.gen_article.delete
 *   GET    /adminapi/demo/gen-article/{id}          show         demo.gen_article.list
 *   POST   /adminapi/demo/gen-article               store        demo.gen_article.create
 *   PUT    /adminapi/demo/gen-article/{id}          update       demo.gen_article.update
 *   DELETE /adminapi/demo/gen-article/{id}          delete       demo.gen_article.delete
 *   PUT    /adminapi/demo/gen-article/{id}/status   status       demo.gen_article.status
 *
 * update 场景用 sometimes|required：字段不传时跳过（局部更新），传了空值必须校验失败。
 * 唯一性不在这里做成校验规则，由 GenArticleService 查重 + 唯一索引异常兜底（spec 决策 12）。
 *
 * genArticleRules() 的 return 必须保持字面量形状（键是字段名、值是字符串，只有 {$required}
 * 一个插值）：M2b 的 OpenAPI 推导器要从这个私有方法里读接口参数（spec §14），改成运行时拼装
 * 就等于把这个模块从 API 文档里摘掉。
 */
class GenArticleController extends Controller
{
    #[Inject]
    protected GenArticleService $genArticleService;

    #[Permission('demo.gen_article.list')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);

        return $this->paginate($this->genArticleService->getGenArticleList((array) $request->get(), $page, $limit));
    }

    #[Permission('demo.gen_article.list')]
    public function show(Request $request, string $id): Response
    {
        return $this->success($this->genArticleService->getGenArticleDetail((int) $id), lang('messages.get_success'));
    }

    #[Permission('demo.gen_article.create')]
    public function store(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->genArticleRules('create'), $this->messages());

        return $this->success($this->genArticleService->createGenArticle($data), lang('messages.create_success'));
    }

    #[Permission('demo.gen_article.update')]
    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->genArticleRules('update'), $this->messages());
        $this->genArticleService->updateGenArticle((int) $id, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Permission('demo.gen_article.delete')]
    public function delete(Request $request, string $id): Response
    {
        $this->genArticleService->deleteGenArticle((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    #[Permission('demo.gen_article.delete')]
    public function batchDelete(Request $request): Response
    {
        $data = $this->validate($this->body($request), [
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ], [
            'ids.required'  => 'demo.gen_article_ids_require',
            'ids.array'     => 'demo.gen_article_ids_require',
            'ids.min'       => 'demo.gen_article_ids_require',
            'ids.*.integer' => 'demo.gen_article_ids_integer',
        ]);
        $this->genArticleService->batchDelete(array_map('intval', (array) $data['ids']));

        return $this->success([], lang('messages.batch_delete_success'));
    }

    #[Permission('demo.gen_article.status')]
    public function status(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), ['status' => 'required|integer|in:0,1'], [
            'status.required' => 'demo.gen_article_status_require',
            'status.integer'  => 'demo.gen_article_status_integer',
            'status.in'       => 'demo.gen_article_status_invalid',
        ]);
        $this->genArticleService->updateStatus((int) $id, (int) $data['status']);

        return $this->success([], lang('messages.status_update_success'));
    }

    /**
     * 字段校验规则。create 场景必填、update 场景 sometimes|required（不传就跳过，传了空值要拒绝）。
     *
     * 这个数组必须一直是字面量：键是字段名、值是字符串，唯一允许的插值是 {$required}。
     * M2b 的 OpenAPI 推导器按这个形状读参数（spec §14）。
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
