<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\service\diy;

use app\repository\diy\DiyLinkRepository;
use app\repository\diy\DiyPageRepository;
use core\base\Service;

/**
 * 装修链接目录：内置基础页 + 自定义装修页 + 链接库。
 */
class LinkCatalogService extends Service
{
    protected DiyPageRepository $pageRepository;
    protected DiyLinkRepository $linkRepository;

    public const BASE_LINKS = [
        ['label' => '首页',     'path' => '/pages/index/index',                      'category' => '基础页面'],
        ['label' => '发现',     'path' => '/pages/discover/index',                   'category' => '基础页面'],
        ['label' => '消息',     'path' => '/pages/message/index',                    'category' => '基础页面'],
        ['label' => '我的',     'path' => '/pages/my/index',                         'category' => '基础页面'],
        ['label' => '登录',     'path' => '/modules/login/pages/login',              'category' => '用户中心'],
        ['label' => '注册',     'path' => '/modules/login/pages/register',           'category' => '用户中心'],
        ['label' => '个人资料', 'path' => '/modules/user/pages/edit-profile',        'category' => '用户中心'],
        ['label' => '修改密码', 'path' => '/modules/user/pages/change-password',     'category' => '用户中心'],
        ['label' => '余额',     'path' => '/modules/user/pages/balance',             'category' => '用户中心'],
        ['label' => '积分',     'path' => '/modules/user/pages/points',              'category' => '用户中心'],
        ['label' => '设置',     'path' => '/modules/user/pages/settings',            'category' => '用户中心'],
        ['label' => '意见反馈', 'path' => '/modules/feedback/pages/feedback',        'category' => '用户中心'],
        ['label' => '关于我们', 'path' => '/modules/about/pages/about',              'category' => '用户中心'],
        ['label' => '公告列表', 'path' => '/modules/announcement/pages/announcement-list', 'category' => '内容'],
        ['label' => '文章列表', 'path' => '/modules/article/pages/article-list',     'category' => '内容'],
        ['label' => '用户协议', 'path' => '/modules/agreement/pages/agreement?code=user_agreement', 'category' => '内容'],
    ];

    /** @return array<int,array{label:string,path:string,category:string,source:string,params_schema:array,external:bool}> */
    public function catalog(): array
    {
        $out = [];
        foreach (self::BASE_LINKS as $b) {
            $out[] = $this->item($b['label'], $b['path'], $b['category'], 'builtin');
        }
        foreach ($this->pageRepository->listCustomLinkPages() as $p) {
            $out[] = $this->item($p['label'], $p['path'], '自定义页面', 'custom-page');
        }
        foreach ($this->linkRepository->listLibraryLinks() as $l) {
            $out[] = $this->item($l['label'], $l['path'], $l['category'], 'library');
        }
        return $out;
    }

    private function item(string $label, string $path, string $category, string $source): array
    {
        return [
            'label'         => $label,
            'path'          => $path,
            'category'      => $category,
            'source'        => $source,
            'params_schema' => [],
            'external'      => (bool) preg_match('#^https?://#i', $path),
        ];
    }
}
