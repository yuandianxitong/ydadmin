<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\AdminLoginLogRepository;
use app\repository\system\AdminOperationLogRepository;
use core\base\Service;
use core\exception\NotFoundException;
use DI\Attribute\Inject;

/**
 * 登录日志与操作日志（契约 §2.8）。两个仓储都受数据权限约束：
 * 范围外的 id 删除时当作不存在（spec §5.3），清空只删范围内可见的行。
 */
class LogService extends Service
{
    #[Inject]
    protected AdminLoginLogRepository $loginLogRepository;

    #[Inject]
    protected AdminOperationLogRepository $operationLogRepository;

    /**
     * @param array<string, mixed> $params 控制器 validate() 的返回值
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getLoginLogList(array $params, int $page, int $limit): array
    {
        return $this->loginLogRepository->getSearchList($params, $page, $limit);
    }

    /**
     * @param array<string, mixed> $params 控制器 validate() 的返回值
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getOperationLogList(array $params, int $page, int $limit): array
    {
        return $this->operationLogRepository->getSearchList($params, $page, $limit);
    }

    public function deleteLoginLog(int $id): void
    {
        if (!$this->loginLogRepository->delete($id)) {
            throw new NotFoundException();
        }
    }

    public function deleteOperationLog(int $id): void
    {
        if (!$this->operationLogRepository->delete($id)) {
            throw new NotFoundException();
        }
    }

    /** @return int 删除条数 */
    public function clearLoginLogs(): int
    {
        return $this->loginLogRepository->clearVisible();
    }

    /** @return int 删除条数 */
    public function clearOperationLogs(): int
    {
        return $this->operationLogRepository->clearVisible();
    }
}
