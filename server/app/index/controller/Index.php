<?php
/* ============================================================
 * 项目：元点系统
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
namespace app\index\controller;

use app\BaseController;

class Index extends BaseController
{
    public function index()
    {
        $ua = request()->header('user-agent', '');
        $isMobile = preg_match('/Mobile|Android|iPhone|iPad|iPod|webOS|BlackBerry|Opera Mini|IEMobile/i', $ua);

        return redirect($isMobile ? '/mobile/' : '/pc/');
    }
}
