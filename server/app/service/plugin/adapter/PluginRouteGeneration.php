<?php

declare(strict_types=1);

namespace app\service\plugin\adapter;

use app\repository\plugin\PluginRouteGenerationRepository;
use core\plugin\contracts\RouteGenerationStore;
use core\plugin\PluginStatus;

class PluginRouteGeneration implements RouteGenerationStore
{
    public const KEY = 'plugin_route_generation';

    private PluginRouteGenerationRepository $repository;

    public function __construct(?PluginRouteGenerationRepository $repository = null)
    {
        $this->repository = $repository ?? new PluginRouteGenerationRepository();
    }

    public function read(): ?int
    {
        try {
            $value = $this->repository->findValue(self::KEY);
        } catch (\Throwable) {
            return null;
        }
        if ($value === null || !is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    /**
     * 这次状态写入是否要把版本号加一。
     *
     * 加一当且仅当：写入的是安装最终态（ENABLED / DISABLED / FAILED，且来源是安装，
     * 不是 INSTALLING 占位），或者写入使「是不是 ENABLED」发生变化。
     */
    public function shouldBump(int $before, int $after, bool $installFinal): bool
    {
        if ($installFinal && in_array($after, [PluginStatus::ENABLED, PluginStatus::DISABLED, PluginStatus::FAILED], true)) {
            return true;
        }

        return ($before === PluginStatus::ENABLED) !== ($after === PluginStatus::ENABLED);
    }

    /**
     * 版本号加一。调用方必须已在数据库事务内（如 `Service::runInTransaction()`），行锁才有意义。
     */
    public function bump(): void
    {
        $row = $this->repository->lockByKey(self::KEY);
        if ($row === null) {
            // 老库没有这行种子：同事务内补一行（初始 1），再按下面的逻辑加一。
            $this->repository->seed(self::KEY);
            $row = $this->repository->lockByKey(self::KEY);
        }
        if ($row === null) {
            throw new \RuntimeException('plugin_route_generation 行无法创建');
        }
        $this->repository->writeValue($row['id'], (string) (((int) $row['config_value']) + 1));
    }
}
