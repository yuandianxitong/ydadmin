<?php
declare(strict_types=1);

namespace app\adminapi\controller\v1\message;

use core\base\Controller;
use app\service\message\MessageService;
use core\attribute\Permission;
use think\Response;

class MessageTemplateController extends Controller
{
    protected MessageService $service;

    /**
     * 模板列表
     */
    #[Permission('message.template.list')]
    public function index(): Response
    {
        $params = $this->getRequestData([
            'keyword' => '',
            'status'  => '',
            'page'    => 1,
            'limit'   => 20,
        ]);
        $result = $this->service->getTemplateList($params);
        return $this->paginate($result);
    }

    /**
     * 模板详情
     */
    #[Permission('message.template.list')]
    public function show(): Response
    {
        $id = (int)$this->request->param('id');
        $result = $this->service->getTemplateDetail($id);
        if (!$result) {
            return $this->error(lang('business.template_not_found'));
        }
        return $this->success(lang('messages.get_success'), $result);
    }

    /**
     * 创建模板
     */
    #[Permission('message.template.create')]
    public function store(): Response
    {
        try {
            $data = $this->request->post();
            $result = $this->service->createTemplate($data);
            return $this->success(lang('messages.create_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 更新模板
     */
    #[Permission('message.template.update')]
    public function update(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $data = $this->request->post();
            $this->service->updateTemplate($id, $data);
            return $this->success(lang('messages.update_success'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除模板
     */
    #[Permission('message.template.delete')]
    public function delete(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $this->service->deleteTemplate($id);
            return $this->success(lang('messages.delete_success'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 测试发送
     */
    #[Permission('message.template.send')]
    public function testSend(): Response
    {
        try {
            $code = (string)$this->request->param('code', '');
            $receivers = $this->request->param('receivers', []);
            $data = $this->request->param('data', []);

            if (empty($code)) {
                return $this->error(lang('business.template_code_required'));
            }

            $result = $this->service->send($code, $receivers, $data);
            return $this->success(lang('messages.send_complete'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
