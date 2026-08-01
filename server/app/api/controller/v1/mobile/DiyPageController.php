<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\api\controller\v1\mobile;

use app\service\diy\DiyPageService;
use core\base\Controller;
use core\exception\BusinessException;
use think\Response;

class DiyPageController extends Controller
{
    protected DiyPageService $diyPageService;

    public function get(): Response
    {
        $key = (string) $this->request->param('key', '');
        if ($key === '') {
            throw new BusinessException('缺少页面标识', 422);
        }
        $page = $this->diyPageService->getPublished($key);
        if ($page === null) {
            throw new BusinessException('页面不存在', 404);
        }
        return $this->success('ok', $page);
    }
}
