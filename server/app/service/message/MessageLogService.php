<?php

declare(strict_types=1);

namespace app\service\message;

use app\repository\message\MessageLogRepository;
use core\base\Service;
use DI\Attribute\Inject;

/**
 * 管理端消息日志列表（M6b spec §4.2）。只读；日志行由 MessageService / MessageDeliveryService 写入。
 * receiver 存的是遮蔽后的值，筛选也只对遮蔽值做 LIKE，拿完整手机号查不到（本意如此）。
 *
 * 容器单例，无实例态。
 */
class MessageLogService extends Service
{
    #[Inject]
    protected MessageLogRepository $messageLogRepository;

    /**
     * @param array<string, mixed> $params 控制器 validate() 的返回值：channel、status、receiver、template_code
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getList(array $params, int $page, int $limit): array
    {
        // 前端清空筛选时带 channel=''、receiver=''：去掉 null 与空串，'0' 保留（status=0 待发是有效筛选）
        $filters = array_filter($params, static fn (mixed $value): bool => $value !== null && $value !== '');

        return $this->messageLogRepository->getAdminList($filters, $page, $limit);
    }
}
