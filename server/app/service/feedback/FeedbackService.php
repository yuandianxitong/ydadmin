<?php

declare(strict_types=1);

namespace app\service\feedback;

use app\repository\feedback\FeedbackRepository;
use app\service\message\MessageService;
use core\base\Service;
use core\context\RequestContext;
use core\exception\BusinessException;
use core\exception\NotFoundException;
use DI\Attribute\Inject;

/**
 * 用户反馈。
 *
 * 只调 Repository：查询条件在 FeedbackRepository。
 * 写操作一律包 runInTransaction()。提交成功后经 afterCommit 发站内信；回复 / 关闭 / 删除不发。
 */
class FeedbackService extends Service
{
    #[Inject]
    protected FeedbackRepository $feedbackRepository;

    #[Inject]
    protected MessageService $messageService;

    /**
     * C 端提交。type 缺省 suggestion；status 固定待处理；user_id 只认调用方传入（控制器取 $request->userId）。
     *
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     * @return array<string, mixed>
     */
    public function submit(int $userId, array $data): array
    {
        $type = trim((string) ($data['type'] ?? ''));
        $images = $data['images'] ?? [];
        $row = [
            'user_id' => $userId,
            'type'    => $type !== '' ? $type : 'suggestion',
            'content' => (string) $data['content'],
            'images'  => is_array($images) ? $images : [],
            'contact' => $data['contact'] ?? null,
            'status'  => FeedbackRepository::STATUS_PENDING,
        ];

        return $this->runInTransaction(function () use ($row, $userId): array {
            $created = $this->feedbackRepository->create($row);
            $id = (int) $created['id'];
            $this->afterCommit(fn () => $this->messageService->sendToUser($userId, 'feedback_received', [], (string) $id));

            return $created;
        });
    }

    /**
     * 管理端列表：keyword、type、status。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getList(array $params, int $page, int $limit): array
    {
        return $this->feedbackRepository->getSearchList($params, $page, $limit);
    }

    /** @return array<string, mixed> */
    public function getDetail(int $id): array
    {
        return $this->findOrFail($id);
    }

    /**
     * C 端本人列表。
     *
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getUserList(int $userId, int $page, int $limit): array
    {
        return $this->feedbackRepository->getUserList($userId, $page, $limit);
    }

    /**
     * C 端详情：非本人与不存在同一 404，响应体不含 content / reply。
     *
     * @return array<string, mixed>
     */
    public function getUserDetail(int $userId, int $id): array
    {
        return $this->feedbackRepository->findForUser($userId, $id)
            ?? throw new NotFoundException(lang('feedback.not_found'));
    }

    public function reply(int $id, string $reply): void
    {
        $existing = $this->findOrFail($id);
        if ((int) $existing['status'] === FeedbackRepository::STATUS_CLOSED) {
            throw new BusinessException(lang('feedback.closed'));
        }

        $this->runInTransaction(function () use ($id, $reply): void {
            $this->feedbackRepository->update($id, [
                'reply'      => $reply,
                'replied_at' => date('Y-m-d H:i:s'),
                'replied_by' => RequestContext::actingUser(),
                'status'     => FeedbackRepository::STATUS_REPLIED,
            ]);
        });
    }

    public function close(int $id): void
    {
        $this->findOrFail($id);

        $this->runInTransaction(function () use ($id): void {
            $this->feedbackRepository->update($id, [
                'status' => FeedbackRepository::STATUS_CLOSED,
            ]);
        });
    }

    public function delete(int $id): void
    {
        $this->findOrFail($id);

        $this->runInTransaction(function () use ($id): void {
            $this->feedbackRepository->delete($id);
        });
    }

    /** @return array<string, mixed> */
    private function findOrFail(int $id): array
    {
        return $this->feedbackRepository->find($id)
            ?? throw new NotFoundException(lang('feedback.not_found'));
    }
}
