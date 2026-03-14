<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\service\feedback;

use app\model\feedback\Feedback;
use app\repository\feedback\FeedbackRepository;
use core\base\Service;
use core\exception\BusinessException;

class FeedbackService extends Service
{
    protected FeedbackRepository $feedbackRepository;

    /**
     * 提交反馈（C端用户）
     */
    public function submit(int $userId, array $data): array
    {
        $feedback = $this->feedbackRepository->create([
            'user_id' => $userId,
            'type'    => $data['type'] ?? 'suggestion',
            'content' => $data['content'],
            'images'  => $data['images'] ?? [],
            'contact' => $data['contact'] ?? '',
            'status'  => Feedback::STATUS_PENDING,
        ]);

        $this->trigger('feedback.created', [
            'feedback_id' => $feedback['id'],
            'user_id'     => $userId,
            'type'        => $data['type'] ?? 'suggestion',
        ]);

        return $feedback;
    }

    /**
     * 获取用户的反馈列表（C端）
     */
    public function getUserList(int $userId, array $params): array
    {
        $page = (int) ($params['page_no'] ?? 1);
        $limit = (int) ($params['page_size'] ?? 10);
        return $this->feedbackRepository->getUserFeedbacks($userId, $page, $limit);
    }

    /**
     * 搜索反馈列表（管理端）
     */
    public function getList(array $params): array
    {
        $page = (int) ($params['page_no'] ?? 1);
        $limit = (int) ($params['page_size'] ?? 20);
        return $this->feedbackRepository->getSearchList($params, $page, $limit);
    }

    /**
     * 反馈详情
     */
    public function detail(int $id): ?array
    {
        return $this->feedbackRepository->find($id);
    }

    /**
     * 回复反馈（管理端）
     */
    public function reply(int $id, int $adminId, string $replyContent): bool
    {
        $feedback = $this->feedbackRepository->findModel($id);
        if (!$feedback) {
            throw new BusinessException(lang('business.record_not_found'));
        }

        if ($feedback->status === Feedback::STATUS_CLOSED) {
            throw new BusinessException(lang('business.feedback_closed'));
        }

        return (bool) $feedback->save([
            'reply'      => $replyContent,
            'replied_at' => date('Y-m-d H:i:s'),
            'replied_by' => $adminId,
            'status'     => Feedback::STATUS_REPLIED,
        ]);
    }

    /**
     * 关闭反馈
     */
    public function close(int $id): bool
    {
        return $this->feedbackRepository->update($id, [
            'status' => Feedback::STATUS_CLOSED,
        ]);
    }

    /**
     * 删除反馈
     */
    public function delete(int $id): bool
    {
        return $this->feedbackRepository->delete($id);
    }
}
