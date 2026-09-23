<?php

declare(strict_types=1);

namespace app\adminapi\controller\diy;

use app\service\diy\DiyPageService;
use app\service\diy\LinkCatalogService;
use core\base\Controller;
use core\context\RequestContext;
use core\exception\ForbiddenException;
use core\permission\Permission;
use core\permission\PermissionCheckerInterface;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * 装修页面。
 *
 * 端点（具名/静态路径必须排在裸 /home 之前；pages/{key} 必须排在 pages/{id} 之前，见 config/route/diy.php）：
 *   GET    /adminapi/diy/home/summary                         homeSummary           diy.home.view
 *   POST   /adminapi/diy/home/publish                         publishHome           diy.home.publish
 *   GET    /adminapi/diy/home/versions                        versions              diy.home.version.view
 *   POST   /adminapi/diy/home/versions/{id}/restore           restoreVersion        diy.home.version.restore
 *   GET    /adminapi/diy/home                                 getHome               diy.home.view
 *   PUT    /adminapi/diy/home                                 saveHome              diy.home.save
 *   GET    /adminapi/diy/widgets                              widgets               diy.home.view
 *   POST   /adminapi/diy/widget-preview                       previewWidget         diy.home.view
 *   GET    /adminapi/diy/link-catalog                         linkCatalog           diy.home.view
 *   GET    /adminapi/diy/pages/{key}/summary                  pageSummary           diy.home.view
 *   GET    /adminapi/diy/pages/{key}/draft                    getDraftByKey         diy.page.view
 *   PUT    /adminapi/diy/pages/{key}/draft                    saveDraftByKey        diy.page.save（key=home 另需 diy.home.save）
 *   POST   /adminapi/diy/pages/{key}/publish                  publishByKey          diy.page.publish（key=home 另需 diy.home.publish）
 *   GET    /adminapi/diy/pages/{key}/versions                 versionsByKey         diy.page.view
 *   POST   /adminapi/diy/pages/{key}/versions/{id}/restore    restoreVersionByKey   diy.page.save（key=home 另需 diy.home.version.restore）
 *   GET    /adminapi/diy/pages                                 listPages             diy.page.view
 *   POST   /adminapi/diy/pages                                 createPage            diy.page.create
 *   POST   /adminapi/diy/pages/{id}/copy                      copyPage              diy.page.create
 *   PUT    /adminapi/diy/pages/{id}                           updatePage            diy.page.update
 *   DELETE /adminapi/diy/pages/{id}                           deletePage            diy.page.delete
 *
 * saveHome()/saveDraftByKey()/createPage()/updatePage()/previewWidget() 的校验规则由同名的 xxxRules() 无参私有方法提供。
 * listPages 禁止 $this->paginate()：前端冻的是 {list,total}。
 */
class DiyPageController extends Controller
{
    #[Inject]
    protected DiyPageService $diyPageService;

    #[Inject]
    protected LinkCatalogService $linkCatalogService;

    #[Inject]
    protected PermissionCheckerInterface $permissionChecker;

    /**
     * 首页同时能从 /diy/home（diy.home.*）和 /diy/pages/home（diy.page.*）两条路打到——
     * 后台编辑器走的就是后一条。注解只认静态权限点，所以这里按 key 再确认一次：
     * 动到首页必须持有对应的 diy.home.*，否则只有「自定义页面」权限的人就能改并发布首页。
     * 个人中心（member）不在此列，它本来就归 diy.page.*。
     */
    private function assertHomeKeyAllowed(string $key, string $homePermission): void
    {
        if ($key !== 'home') {
            return;
        }

        $adminId = RequestContext::actingUser();
        if ($adminId <= 0 || !$this->permissionChecker->check($adminId, $homePermission)) {
            throw new ForbiddenException();
        }
    }

    #[Permission('diy.home.view')]
    public function homeSummary(): Response
    {
        return $this->success($this->diyPageService->getHomeSummary());
    }

    #[Permission('diy.home.view')]
    public function getHome(): Response
    {
        return $this->success($this->diyPageService->getHomeDraft());
    }

    #[Permission('diy.home.save')]
    public function saveHome(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->saveHomeRules());
        $this->diyPageService->saveHomeDraft(
            (array) ($data['components'] ?? []),
            $this->pageSettings($data)
        );

        return $this->success([], lang('messages.update_success'));
    }

    #[Permission('diy.home.publish')]
    public function publishHome(): Response
    {
        $this->diyPageService->publishHome();

        return $this->success([], lang('messages.success'));
    }

    #[Permission('diy.home.version.view')]
    public function versions(): Response
    {
        return $this->success($this->diyPageService->listHomeVersions());
    }

    #[Permission('diy.home.version.restore')]
    public function restoreVersion(Request $request, string $id): Response
    {
        $this->diyPageService->restoreHomeVersion((int) $id);

        return $this->success([], lang('messages.success'));
    }

    #[Permission('diy.home.view')]
    public function pageSummary(Request $request, string $key): Response
    {
        return $this->success($this->diyPageService->getPageSummary($key));
    }

    #[Permission('diy.page.view')]
    public function getDraftByKey(Request $request, string $key): Response
    {
        return $this->success($this->diyPageService->getDraft($key));
    }

    #[Permission('diy.page.save')]
    public function saveDraftByKey(Request $request, string $key): Response
    {
        $this->assertHomeKeyAllowed($key, 'diy.home.save');
        $data = $this->validate($this->body($request), $this->saveDraftByKeyRules());
        $this->diyPageService->saveDraft(
            $key,
            (array) ($data['components'] ?? []),
            $this->pageSettings($data)
        );

        return $this->success([], lang('messages.update_success'));
    }

    #[Permission('diy.page.publish')]
    public function publishByKey(Request $request, string $key): Response
    {
        $this->assertHomeKeyAllowed($key, 'diy.home.publish');
        $this->diyPageService->publish($key);

        return $this->success([], lang('messages.success'));
    }

    #[Permission('diy.page.view')]
    public function versionsByKey(Request $request, string $key): Response
    {
        return $this->success($this->diyPageService->listPageVersions($key));
    }

    #[Permission('diy.page.save')]
    public function restoreVersionByKey(Request $request, string $key, string $id): Response
    {
        $this->assertHomeKeyAllowed($key, 'diy.home.version.restore');
        $this->diyPageService->restorePageVersion($key, (int) $id);

        return $this->success([], lang('messages.success'));
    }

    #[Permission('diy.home.view')]
    public function widgets(): Response
    {
        return $this->success($this->diyPageService->widgets());
    }

    #[Permission('diy.home.view')]
    public function previewWidget(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->previewWidgetRules());

        return $this->success($this->diyPageService->previewWidget(
            (string) $data['type'],
            is_array($data['props'] ?? null) ? $data['props'] : []
        ));
    }

    #[Permission('diy.home.view')]
    public function linkCatalog(): Response
    {
        return $this->success(['links' => $this->linkCatalogService->catalog()]);
    }

    #[Permission('diy.page.view')]
    public function listPages(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request, 10);
        $published = $request->get('published', '');

        return $this->success($this->diyPageService->listPages(
            $page,
            $limit,
            (string) $request->get('keyword', ''),
            $published === '' || $published === null ? null : (bool) (int) $published,
        ));
    }

    #[Permission('diy.page.create')]
    public function createPage(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->createPageRules());

        return $this->success($this->diyPageService->createPage($data), lang('messages.create_success'));
    }

    #[Permission('diy.page.create')]
    public function copyPage(Request $request, string $id): Response
    {
        return $this->success($this->diyPageService->copyPage((int) $id), lang('messages.create_success'));
    }

    #[Permission('diy.page.update')]
    public function updatePage(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->updatePageRules());
        $this->diyPageService->updatePage((int) $id, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Permission('diy.page.delete')]
    public function deletePage(Request $request, string $id): Response
    {
        $this->diyPageService->deletePage((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    /**
     * @return array<string, string>
     */
    private function previewWidgetRules(): array
    {
        return [
            'type'  => 'required|string',
            'props' => 'nullable|array',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function saveHomeRules(): array
    {
        return $this->draftRules();
    }

    /**
     * @return array<string, string>
     */
    private function saveDraftByKeyRules(): array
    {
        return $this->draftRules();
    }

    /**
     * @return array<string, string>
     */
    private function draftRules(): array
    {
        return [
            'components'    => 'required|array',
            'page_settings' => 'nullable|array',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function createPageRules(): array
    {
        return [
            'title'    => 'required|string|max:100',
            'page_key' => 'required|string',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function updatePageRules(): array
    {
        return [
            'title'    => 'sometimes|required|string|max:100',
            'page_key' => 'sometimes|required|string',
            'status'   => 'sometimes|in:0,1',
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function pageSettings(array $data): array
    {
        $settings = $data['page_settings'] ?? [];

        return is_array($settings) ? $settings : [];
    }
}
