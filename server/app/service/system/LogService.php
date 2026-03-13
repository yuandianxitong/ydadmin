<?php
declare(strict_types=1);

namespace app\service\system;

use app\repository\system\AdminLoginLogRepository;
use app\repository\system\AdminOperationLogRepository;
use core\base\Service;

class LogService extends Service
{
    protected AdminLoginLogRepository $loginLogRepository;
    protected AdminOperationLogRepository $operationLogRepository;

    /**
     * 获取登录日志列表
     */
    public function getLoginLogList(array $params): array
    {
        $page = (int) ($params['page'] ?? 1);
        $limit = (int) ($params['limit'] ?? 15);

        return $this->loginLogRepository->getSearchList($params, $page, $limit);
    }

    /**
     * 获取操作日志列表
     */
    public function getOperationLogList(array $params): array
    {
        $page = (int) ($params['page'] ?? 1);
        $limit = (int) ($params['limit'] ?? 15);

        return $this->operationLogRepository->getSearchList($params, $page, $limit);
    }

    /**
     * 删除登录日志
     */
    public function deleteLoginLog(int $id): bool
    {
        return $this->loginLogRepository->delete($id);
    }

    /**
     * 删除操作日志
     */
    public function deleteOperationLog(int $id): bool
    {
        return $this->operationLogRepository->delete($id);
    }

    /**
     * 清空登录日志
     */
    public function clearLoginLog(): bool
    {
        return $this->loginLogRepository->clear();
    }

    /**
     * 清空操作日志
     */
    public function clearOperationLog(): bool
    {
        return $this->operationLogRepository->clear();
    }
}
