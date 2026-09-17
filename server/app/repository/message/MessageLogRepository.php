<?php

declare(strict_types=1);

namespace app\repository\message;

use app\model\message\MessageLog;
use core\base\Model;
use core\base\Repository;
use core\support\Like;

/**
 * 消息发送日志仓储（M6b spec §2.2、§4.4）。不设 $dataScoped：日志是全局运营数据（spec §2.5）。
 */
class MessageLogRepository extends Repository
{
    public const STATUS_PENDING = MessageLog::STATUS_PENDING;

    public const STATUS_SUCCESS = MessageLog::STATUS_SUCCESS;

    public const STATUS_FAILED = MessageLog::STATUS_FAILED;

    public const CHANNEL_SMS = MessageLog::CHANNEL_SMS;

    public const CHANNEL_WECHAT_OFFICIAL = MessageLog::CHANNEL_WECHAT_OFFICIAL;

    public const CHANNEL_WECHAT_MINI = MessageLog::CHANNEL_WECHAT_MINI;

    public const CHANNEL_SITE = MessageLog::CHANNEL_SITE;

    private const ERROR_MSG_MAX = 255;

    protected ?string $creatorColumn = null;

    /** @var list<string> */
    protected array $sortable = ['id', 'created_at'];

    protected function getModel(): Model
    {
        return new MessageLog();
    }

    /**
     * 事务内按 id 行锁读。lockForUpdate() 单独成句的原因见 UserRepository::findForUpdate()。
     *
     * @return array<string, mixed>|null
     */
    public function findForUpdate(int $id): ?array
    {
        $query = $this->query()->where($this->qualify('id'), $id);
        $query->lockForUpdate();
        $row = $query->first();

        return $row === null ? null : $row->toArray();
    }

    public function incrementAttempts(int $id): void
    {
        $this->query()->where($this->qualify('id'), $id)->increment('attempts');
    }

    /**
     * 写发送结果：条件更新 WHERE id=? AND status=0，并发重复投递时只有一个结果落库（计划设计决定 10）。
     * 成功时写 sent_at；error_msg 按字符截断到 255（列宽）。返回是否写入。
     */
    public function finishIfPending(int $id, int $status, string $content, string $errorMsg): bool
    {
        $data = [
            'status'    => $status,
            'content'   => $content,
            'error_msg' => mb_substr($errorMsg, 0, self::ERROR_MSG_MAX, 'UTF-8'),
        ];
        if ($status === self::STATUS_SUCCESS) {
            $data['sent_at'] = date('Y-m-d H:i:s');
        }

        return $this->query()
            ->where($this->qualify('id'), $id)
            ->where($this->qualify('status'), self::STATUS_PENDING)
            ->update($data) > 0;
    }

    /**
     * 管理端列表：channel、status、template_code 精确过滤，receiver 模糊匹配（遮蔽值，% 与 _ 按字面）；空串不过滤；id 倒序。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getAdminList(array $params, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query();

        foreach (['channel', 'template_code'] as $column) {
            $value = trim((string) ($params[$column] ?? ''));
            if ($value !== '') {
                $query->where($this->qualify($column), $value);
            }
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where($this->qualify('status'), (int) $params['status']);
        }
        $receiver = trim((string) ($params['receiver'] ?? ''));
        if ($receiver !== '') {
            $query->where($this->qualify('receiver'), 'like', Like::contains($receiver));
        }

        $total = (clone $query)->count();
        $list = $this->applyOrder($query, 'id desc')->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }
}
