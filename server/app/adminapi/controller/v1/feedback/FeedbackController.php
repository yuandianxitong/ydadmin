<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\controller\v1\feedback;

use core\base\Controller;
use core\attribute\Permission;
use app\service\feedback\FeedbackService;
use app\adminapi\validate\v1\feedback\FeedbackValidate;
use think\Response;

class FeedbackController extends Controller
{
    protected FeedbackService $feedbackService;

    /**
     * 反馈列表
     */
    #[Permission('feedback.list')]
    public function list(): Response
    {
        $params = $this->getRequestData([
            'page_no'   => 1,
            'page_size' => 20,
            'status'    => '',
            'type'      => '',
            'keyword'   => '',
        ]);
        $result = $this->feedbackService->getList($params);
        return $this->paginate($result);
    }

    /**
     * 反馈详情
     */
    #[Permission('feedback.detail')]
    public function detail(): Response
    {
        $id = (int) $this->request->param('id');
        $result = $this->feedbackService->detail($id);
        if (!$result) {
            return $this->error(lang('business.record_not_found'));
        }
        return $this->success(lang('messages.get_success'), $result);
    }

    /**
     * 回复反馈
     */
    #[Permission('feedback.reply')]
    public function reply(): Response
    {
        $data = $this->request->only(['id', 'reply']);
        $this->validate($data, FeedbackValidate::class, [], false, 'reply');
        $adminId = $this->getUserId();
        $this->feedbackService->reply((int) $data['id'], $adminId, $data['reply']);
        return $this->success(lang('messages.operation_success'));
    }

    /**
     * 关闭反馈
     */
    #[Permission('feedback.close')]
    public function close(): Response
    {
        $id = (int) $this->request->param('id');
        $this->feedbackService->close($id);
        return $this->success(lang('messages.operation_success'));
    }

    /**
     * 删除反馈
     */
    #[Permission('feedback.delete')]
    public function delete(): Response
    {
        $id = (int) $this->request->param('id');
        $this->feedbackService->delete($id);
        return $this->success(lang('messages.delete_success'));
    }
}
