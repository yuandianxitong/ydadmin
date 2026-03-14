<?php
declare(strict_types=1);

namespace app\api\controller\v1\announcement;

use core\base\Controller;
use app\service\announcement\AnnouncementService;
use think\Response;

class AnnouncementController extends Controller
{
    protected AnnouncementService $announcementService;

    /**
     * 获取已发布的公告列表
     */
    public function list(): Response
    {
        try {
            $params = $this->request->only(['page_no', 'page_size']);
            $result = $this->announcementService->getPublishedList($params);
            return $this->paginate($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 公告详情
     */
    public function detail(int $id): Response
    {
        try {
            $result = $this->announcementService->detail($id);
            if (!$result || (int) ($result['status'] ?? 0) !== 1) {
                return $this->error(lang('business.record_not_found'));
            }
            return $this->success(lang('messages.get_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
