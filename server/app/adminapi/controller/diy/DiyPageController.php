<?php

declare(strict_types=1);

namespace app\adminapi\controller\diy;

use app\service\diy\DiyPageService;
use core\base\Controller;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * 装修页面（本任务只接通 home / widgets / 按 key 的 draft|publish|versions|summary）。
 *
 * 端点（具名/静态路径必须排在裸 /home 之前注册，见 config/route/diy.php）：
 *   GET    /adminapi/diy/home/summary                         homeSummary           diy.home.view
 *   POST   /adminapi/diy/home/publish                         publishHome           diy.home.publish
 *   GET    /adminapi/diy/home/versions                        versions              diy.home.version.view
 *   POST   /adminapi/diy/home/versions/{id}/restore           restoreVersion        diy.home.version.restore
 *   GET    /adminapi/diy/home                                 getHome               diy.home.view
 *   PUT    /adminapi/diy/home                                 saveHome              diy.home.save
 *   GET    /adminapi/diy/widgets                              widgets               diy.home.view
 *   GET    /adminapi/diy/pages/{key}/summary                  pageSummary           diy.home.view
 *   GET    /adminapi/diy/pages/{key}/draft                    getDraftByKey         diy.page.view
 *   PUT    /adminapi/diy/pages/{key}/draft                    saveDraftByKey        diy.page.save
 *   POST   /adminapi/diy/pages/{key}/publish                  publishByKey          diy.page.publish
 *   GET    /adminapi/diy/pages/{key}/versions                 versionsByKey         diy.page.view
 *   POST   /adminapi/diy/pages/{key}/versions/{id}/restore    restoreVersionByKey   diy.page.save
 *
 * saveHome()/saveDraftByKey() 的校验规则由同名的 xxxRules() 无参私有方法提供。
 */
class DiyPageController extends Controller
{
    #[Inject]
    protected DiyPageService $diyPageService;

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
        $this->diyPageService->restorePageVersion($key, (int) $id);

        return $this->success([], lang('messages.success'));
    }

    #[Permission('diy.home.view')]
    public function widgets(): Response
    {
        return $this->success($this->diyPageService->widgets());
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
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function pageSettings(array $data): array
    {
        $settings = $data['page_settings'] ?? [];

        return is_array($settings) ? $settings : [];
    }
}
