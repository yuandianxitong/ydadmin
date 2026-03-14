<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\controller\v1\announcement;

use core\base\Controller;
use core\attribute\Permission;
use app\service\announcement\AnnouncementService;
use app\adminapi\validate\v1\announcement\AnnouncementValidate;
use think\Response;

class AnnouncementController extends Controller
{
    protected AnnouncementService $announcementService;

    /**
     * 公告列表
     */
    #[Permission('announcement.list')]
    public function list(): Response
    {
        $params = $this->getRequestData([
            'page_no'   => 1,
            'page_size' => 20,
            'status'    => '',
            'type'      => '',
            'keyword'   => '',
        ]);
        $result = $this->announcementService->getList($params);
        return $this->paginate($result);
    }

    /**
     * 公告详情
     */
    #[Permission('announcement.detail')]
    public function detail(): Response
    {
        $id = (int) $this->request->param('id');
        $result = $this->announcementService->detail($id);
        if (!$result) {
            return $this->error(lang('business.record_not_found'));
        }
        return $this->success(lang('messages.get_success'), $result);
    }

    /**
     * 创建公告
     */
    #[Permission('announcement.create')]
    public function create(): Response
    {
        $data = $this->request->only(['title', 'content', 'type', 'status', 'sort']);
        $this->validate($data, AnnouncementValidate::class, [], false, 'create');
        $data['admin_id'] = $this->getUserId();
        $result = $this->announcementService->create($data);
        return $this->success(lang('messages.create_success'), $result);
    }

    /**
     * 更新公告
     */
    #[Permission('announcement.update')]
    public function update(): Response
    {
        $id = (int) $this->request->param('id');
        $data = $this->request->only(['title', 'content', 'type', 'status', 'sort']);
        $this->validate($data, AnnouncementValidate::class, [], false, 'update');
        $this->announcementService->update($id, $data);
        return $this->success(lang('messages.update_success'));
    }

    /**
     * 更新公告状态
     */
    #[Permission('announcement.status')]
    public function updateStatus(): Response
    {
        $id = (int) $this->request->param('id');
        $status = (int) $this->request->post('status');
        $this->announcementService->updateStatus($id, $status);
        return $this->success(lang('messages.operation_success'));
    }

    /**
     * 删除公告
     */
    #[Permission('announcement.delete')]
    public function delete(): Response
    {
        $id = (int) $this->request->param('id');
        $this->announcementService->delete($id);
        return $this->success(lang('messages.delete_success'));
    }
}
