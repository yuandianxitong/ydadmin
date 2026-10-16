<?php

declare(strict_types=1);

namespace app\repository\system;

use app\model\system\ConfigurationState;
use core\base\Model;
use core\base\Repository;

final class ConfigurationStateRepository extends Repository
{
    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new ConfigurationState();
    }

    public function current(): int
    {
        $revision = $this->query()->whereKey(1)->value('revision');
        if ($revision === null) {
            throw new \RuntimeException('缺少配置版本，请执行 php webman yd:update');
        }
        return (int) $revision;
    }

    /** 由 Service 在配置变更事务内调用，版本与配置一起提交。 */
    public function advance(): void
    {
        if (\support\Db::connection()->transactionLevel() === 0) {
            throw new \LogicException('配置版本必须在配置变更事务内更新');
        }
        if ($this->query()->whereKey(1)->increment('revision') !== 1) {
            throw new \RuntimeException('缺少配置版本，请执行 php webman yd:update');
        }
    }
}
