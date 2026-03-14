<?php
declare(strict_types=1);

namespace app\api\controller\v1\region;

use core\base\Controller;
use app\service\region\RegionService;
use think\Response;

class RegionController extends Controller
{
    protected RegionService $regionService;

    /**
     * 获取地区树
     */
    public function tree(): Response
    {
        try {
            $result = $this->regionService->getTree();
            return $this->success(lang('messages.get_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取子级地区列表
     */
    public function children(): Response
    {
        try {
            $parentId = (int) $this->request->param('parent_id', 0);
            $result = $this->regionService->getByParentId($parentId);
            return $this->success(lang('messages.get_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
