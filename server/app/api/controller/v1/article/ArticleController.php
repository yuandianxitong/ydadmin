<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\api\controller\v1\article;

use core\base\Controller;
use app\service\article\ArticleService;
use think\Response;

class ArticleController extends Controller
{
    protected ArticleService $articleService;

    /**
     * 文章列表（已发布）
     */
    public function getList(): Response
    {
        $params = $this->getRequestData([
            'page_no'     => 1,
            'page_size'   => 10,
            'category_id' => '',
        ]);
        $result = $this->articleService->getPublishedList($params);
        return $this->paginate($result);
    }

    /**
     * 文章详情
     */
    public function getDetail(): Response
    {
        $id = (int) $this->request->param('id');
        $result = $this->articleService->getArticleDetail($id);
        return $this->success(lang('messages.get_success'), $result);
    }
}
