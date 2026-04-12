<?php
declare(strict_types=1);

namespace app\repository\user;

use app\model\user\BalanceLog;
use core\base\Model;

class BalanceLogRepository extends AbstractLedgerLogRepository
{
    protected function getModel(): Model
    {
        return new BalanceLog();
    }

    /**
     * 判断 source（业务来源标识）是否已存在，用于幂等性校验
     */
    public function existsBySource(string $source, ?int $userId = null): bool
    {
        $query = $this->model->where('source', $source);
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        return $query->count() > 0;
    }
}
