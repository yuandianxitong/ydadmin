<?php
declare(strict_types=1);

namespace app\api\controller\v1\version;

use core\base\Controller;
use app\service\version\AppVersionService;
use think\Response;

class VersionController extends Controller
{
    protected AppVersionService $appVersionService;

    /**
     * 检查版本更新
     */
    public function check(): Response
    {
        try {
            $platform = (string) $this->request->param('platform', '');
            $currentVersion = (int) $this->request->param('version_code', 0);

            if (empty($platform) || $currentVersion <= 0) {
                return $this->error('请提供平台和当前版本号');
            }

            $result = $this->appVersionService->checkUpdate($platform, $currentVersion);
            return $this->success(lang('messages.get_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
