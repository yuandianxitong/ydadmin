<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\controller\v1\diy;

use app\service\diy\DiyPageService;
use app\service\diy\LinkCatalogService;
use core\attribute\Permission;
use core\base\Controller;
use core\diy\DiyWidgetRegistry;
use think\Response;

class DiyPageController extends Controller
{
    protected DiyPageService $diyPageService;
    protected LinkCatalogService $linkCatalogService;

    #[Permission('diy.home.view')]
    public function getHome(): Response
    {
        return $this->success('ok', $this->diyPageService->getHomeDraft());
    }

    #[Permission('diy.home.save')]
    public function saveHome(): Response
    {
        $payload = $this->request->put();
        $this->diyPageService->saveHomeDraft(
            (array) ($payload['components'] ?? []),
            (array) ($payload['page_settings'] ?? [])
        );
        return $this->success('保存成功');
    }

    #[Permission('diy.home.publish')]
    public function publishHome(): Response
    {
        $this->diyPageService->publishHome((int) $this->getUserId());
        return $this->success('发布成功');
    }

    #[Permission('diy.home.version.view')]
    public function versions(): Response
    {
        return $this->success('ok', $this->diyPageService->listHomeVersions());
    }

    #[Permission('diy.home.view')]
    public function homeSummary(): Response
    {
        return $this->success('ok', $this->diyPageService->getHomeSummary());
    }

    #[Permission('diy.home.view')]
    public function pageSummary(): Response
    {
        return $this->success('ok', $this->diyPageService->getPageSummary((string) $this->request->param('key')));
    }

    #[Permission('diy.home.version.restore')]
    public function restoreVersion(): Response
    {
        $this->diyPageService->restoreHomeVersion((int) $this->request->param('id'));
        return $this->success('已回滚到草稿');
    }

    #[Permission('diy.page.view')]
    public function listPages(): Response
    {
        $published = $this->request->param('published', '');
        return $this->success('ok', $this->diyPageService->listPages(
            (int) $this->request->param('page', 1),
            (int) $this->request->param('limit', 10),
            (string) $this->request->param('keyword', ''),
            $published === '' ? null : (bool) (int) $published,
        ));
    }

    #[Permission('diy.page.create')]
    public function copyPage(): Response
    {
        $id = $this->diyPageService->copyPage((int) $this->request->param('id'));
        return $this->success('复制成功', ['id' => $id]);
    }

    #[Permission('diy.page.create')]
    public function createPage(): Response
    {
        $p = $this->request->post();
        $id = $this->diyPageService->createPage((string) ($p['title'] ?? ''), (string) ($p['page_key'] ?? ''));
        return $this->success('创建成功', ['id' => $id]);
    }

    #[Permission('diy.page.update')]
    public function updatePage(): Response
    {
        $id = (int) $this->request->param('id');
        $p  = $this->request->put();
        if (isset($p['title'])) {
            $this->diyPageService->renamePage($id, (string) $p['title']);
        }
        if (isset($p['page_key'])) {
            $this->diyPageService->updateSlug($id, (string) $p['page_key']);
        }
        if (isset($p['status'])) {
            $this->diyPageService->setStatus($id, (int) $p['status']);
        }
        return $this->success('保存成功');
    }

    #[Permission('diy.page.delete')]
    public function deletePage(): Response
    {
        $this->diyPageService->deletePage((int) $this->request->param('id'));
        return $this->success('已删除');
    }

    #[Permission('diy.page.view')]
    public function getDraftByKey(): Response
    {
        return $this->success('ok', $this->diyPageService->getDraft((string) $this->request->param('key')));
    }

    #[Permission('diy.page.save')]
    public function saveDraftByKey(): Response
    {
        $key = (string) $this->request->param('key');
        $payload = $this->request->put();
        // 系统页（home/member）用 home 权限语义；自定义页用 page.save
        $this->diyPageService->saveDraft($key, (array) ($payload['components'] ?? []), (array) ($payload['page_settings'] ?? []));
        return $this->success('保存成功');
    }

    #[Permission('diy.page.publish')]
    public function publishByKey(): Response
    {
        $this->diyPageService->publish((string) $this->request->param('key'), (int) $this->getUserId());
        return $this->success('发布成功');
    }

    #[Permission('diy.page.view')]
    public function versionsByKey(): Response
    {
        return $this->success('ok', $this->diyPageService->listPageVersions((string) $this->request->param('key')));
    }

    #[Permission('diy.page.save')]
    public function restoreVersionByKey(): Response
    {
        $this->diyPageService->restorePageVersion((string) $this->request->param('key'), (int) $this->request->param('id'));
        return $this->success('已回滚到草稿');
    }

    #[Permission('diy.home.view')]
    public function linkCatalog(): Response
    {
        return $this->success('ok', ['links' => $this->linkCatalogService->catalog()]);
    }

    #[Permission('diy.home.view')]
    public function widgets(): Response
    {
        return $this->success('ok', [
            'builtins'     => DiyWidgetRegistry::TYPES,
            'plugins'      => [],
            'member_stats' => [
                ['key' => 'user.balance', 'label' => '余额'],
                ['key' => 'user.points', 'label' => '积分'],
            ],
        ]);
    }
}
