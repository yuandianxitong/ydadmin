<?php
declare(strict_types=1);

namespace app\repository\user;

use app\model\user\PointsLog;
use core\base\Model;

class PointsLogRepository extends AbstractLedgerLogRepository
{
    protected function getModel(): Model
    {
        return new PointsLog();
    }
}
