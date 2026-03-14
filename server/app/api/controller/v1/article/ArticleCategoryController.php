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
use app\service\article\ArticleCategoryService;
use think\Response;

class ArticleCategoryController extends Controller
{
    protected ArticleCategoryService $articleCategoryService;

    /**
     * 文章分类列表（仅启用）
     */
    public function getList(): Response
    {
        $result = $this->articleCategoryService->getList(true);
        return $this->success(lang('messages.get_success'), $result);
    }
}
