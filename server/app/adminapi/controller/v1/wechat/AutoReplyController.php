<?php
declare(strict_types=1);

namespace app\adminapi\controller\v1\wechat;

use core\base\Controller;
use app\service\wechat\AutoReplyService;
use core\attribute\Permission;
use think\Response;

class AutoReplyController extends Controller
{
    protected AutoReplyService $service;

    /**
     * 自动回复列表
     */
    #[Permission('wechat.auto-reply.list')]
    public function index(): Response
    {
        $params = $this->getRequestData([
            'type'    => '',
            'status'  => '',
            'keyword' => '',
            'page'    => 1,
            'limit'   => 20,
        ]);
        $result = $this->service->getList($params);
        return $this->paginate($result);
    }

    /**
     * 自动回复详情
     */
    #[Permission('wechat.auto-reply.list')]
    public function show(): Response
    {
        $id = (int)$this->request->param('id');
        $result = $this->service->getDetail($id);
        if (!$result) {
            return $this->error(lang('business.auto_reply_not_found'));
        }
        return $this->success(lang('messages.get_success'), $result);
    }

    /**
     * 创建自动回复
     */
    #[Permission('wechat.auto-reply.create')]
    public function store(): Response
    {
        try {
            $data = $this->request->post();
            $result = $this->service->create($data);
            return $this->success(lang('messages.create_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 更新自动回复
     */
    #[Permission('wechat.auto-reply.update')]
    public function update(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $data = $this->request->post();
            $this->service->update($id, $data);
            return $this->success(lang('messages.update_success'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除自动回复
     */
    #[Permission('wechat.auto-reply.delete')]
    public function delete(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $this->service->delete($id);
            return $this->success(lang('messages.delete_success'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
