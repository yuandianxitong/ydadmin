<?php
declare(strict_types=1);

namespace app\api\controller\v1\feedback;

use core\base\Controller;
use app\service\feedback\FeedbackService;
use think\Response;

class FeedbackController extends Controller
{
    protected FeedbackService $feedbackService;

    /**
     * 提交反馈
     */
    public function submit(): Response
    {
        try {
            $data = $this->request->only(['type', 'content', 'images', 'contact']);

            if (empty($data['content'])) {
                return $this->error(lang('validation.content_require'));
            }

            $userId = $this->getUserId();
            $result = $this->feedbackService->submit($userId, $data);
            return $this->success(lang('messages.submit_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 反馈列表
     */
    public function list(): Response
    {
        try {
            $params = $this->request->only(['page_no', 'page_size']);
            $userId = $this->getUserId();
            $result = $this->feedbackService->getUserList($userId, $params);
            return $this->paginate($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 反馈详情
     */
    public function detail(int $id): Response
    {
        try {
            $result = $this->feedbackService->detail($id);
            return $this->success(lang('messages.get_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
