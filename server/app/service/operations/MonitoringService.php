<?php

declare(strict_types=1);

namespace app\service\operations;

use app\repository\operations\MonitoringRepository;
use app\repository\operations\OutboxRepository;
use core\auth\PermissionCheckerInterface;
use core\base\Service;
use core\context\RequestContext;
use core\exception\ForbiddenException;
use core\observability\Heartbeat;
use DI\Attribute\Inject;
use support\Redis;
use Workerman\RedisQueue\Client;

final class MonitoringService extends Service
{
    #[Inject]
    protected MonitoringRepository $repository;
    #[Inject]
    protected OutboxRepository $outbox;
    #[Inject]
    protected Heartbeat $heartbeat;
    #[Inject]
    protected PrivateTaskFiles $files;
    #[Inject]
    protected PermissionCheckerInterface $permissions;

    /**
     * @return array{ready: bool, checks: array<string, bool>} */
    public function readiness(): array
    {
        $checks = ['database' => false, 'redis' => false, 'workers' => false, 'storage' => false];
        try {
            $checks['database'] = $this->repository->databaseReady();
        } catch (\Throwable) {
        }
        try {
            Redis::connection()->command('ping');
            $checks['redis'] = true;
            $workers = $this->heartbeat->snapshot();
            // 长命令会阻塞该进程的 Timer；未过期执行租约仍表示它处于受控执行期。
            if ($workers['cron'] === 0 && $this->outbox->hasActiveLease('cron-job')) {
                $workers['cron'] = 1;
            }
            $checks['workers'] = !config('operations.require_workers_ready', true) || !in_array(0, $workers, true);
        } catch (\Throwable) {
        }
        try {
            $checks['storage'] = is_writable($this->files->directory());
        } catch (\Throwable) {
        }

        return ['ready' => !in_array(false, $checks, true), 'checks' => $checks];
    }

    /**
     * @return array<string, mixed> */
    public function snapshot(): array
    {
        if (!$this->permissions->isSuperAdmin(RequestContext::actingUser())) {
            throw new ForbiddenException(lang('auth.permission_denied'));
        }
        $queues = [];
        $prefix = (string) config('plugin.webman.redis-queue.redis.default.options.prefix', '');
        foreach (array_keys((array) config('queue.queues')) as $queue) {
            $queues[$queue] = (int) Redis::lLen($prefix . Client::QUEUE_WAITING . $queue);
        }

        return [
            'readiness' => $this->readiness(), 'workers' => $this->heartbeat->snapshot(),
            'outbox' => $this->outbox->statistics(), 'queues' => $queues,
            'queue_delayed' => (int) Redis::zCard($prefix . Client::QUEUE_DELAYED),
            'queue_failed' => (int) Redis::lLen($prefix . Client::QUEUE_FAILED),
            'http_current_minute' => Redis::hGetAll('ops:http:' . date('YmdHi')),
        ];
    }
}
