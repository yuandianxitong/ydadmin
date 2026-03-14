<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\controller\v1\article;

use core\base\Controller;
use core\attribute\Permission;
use app\service\article\ArticleService;
use app\adminapi\validate\v1\article\ArticleValidate;
use think\Response;

class ArticleController extends Controller
{
    protected ArticleService $articleService;

    /**
     * 文章列表
     */
    #[Permission('article.list')]
    public function list(): Response
    {
        $params = $this->getRequestData([
            'page_no'     => 1,
            'page_size'   => 20,
            'keyword'     => '',
            'category_id' => '',
            'status'      => '',
        ]);
        $result = $this->articleService->getArticleList($params);
        return $this->paginate($result);
    }

    /**
     * 文章详情
     */
    #[Permission('article.detail')]
    public function detail(): Response
    {
        $id = (int) $this->request->param('id');
        $result = $this->articleService->getArticleDetail($id);
        return $this->success(lang('messages.get_success'), $result);
    }

    /**
     * 创建文章
     */
    #[Permission('article.create')]
    public function create(): Response
    {
        $data = $this->request->only([
            'title', 'category_id', 'cover', 'summary', 'content',
            'tags', 'author', 'status', 'publish_at',
        ]);
        $this->validate($data, ArticleValidate::class, [], false, 'create');
        $data['admin_id'] = $this->getUserId();
        $result = $this->articleService->createArticle($data);
        return $this->success(lang('messages.create_success'), $result);
    }

    /**
     * 更新文章
     */
    #[Permission('article.update')]
    public function update(): Response
    {
        $id = (int) $this->request->param('id');
        $data = $this->request->only([
            'title', 'category_id', 'cover', 'summary', 'content',
            'tags', 'author', 'status', 'publish_at',
        ]);
        $this->validate($data, ArticleValidate::class, [], false, 'update');
        $this->articleService->updateArticle($id, $data);
        return $this->success(lang('messages.update_success'));
    }

    /**
     * 删除文章
     */
    #[Permission('article.delete')]
    public function delete(): Response
    {
        $id = (int) $this->request->param('id');
        $this->articleService->deleteArticle($id);
        return $this->success(lang('messages.delete_success'));
    }

    /**
     * 更新文章状态
     */
    #[Permission('article.status')]
    public function updateStatus(): Response
    {
        $id = (int) $this->request->param('id');
        $status = (int) $this->request->post('status');
        $this->articleService->updateStatus($id, $status);
        return $this->success(lang('messages.operation_success'));
    }
}
