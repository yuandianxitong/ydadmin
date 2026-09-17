<?php

declare(strict_types=1);

namespace app\queue\redis_slow;

use app\queue\ConsumerBase;
use app\service\message\MessageDeliveryService;
use DI\Attribute\Inject;
use support\Log;

/**
 * message-send 队列（M6b spec §4.4）：max_attempts = 3。确定失败在 deliver() 里就落为 status=2、不抛出；
 * 只有暂时失败会抛到这里交给 M3 重试。最后一次仍失败时，除 M3 写 failed_jobs 外，还要把日志行置为「重试耗尽」。
 *
 * 放在慢队列目录：调用短信、微信接口单次可能耗时数秒，不和操作日志抢同一组消费进程。
 */
final class MessageSendConsumer extends ConsumerBase
{
    public string $queue = 'message-send';

    #[Inject]
    protected MessageDeliveryService $deliveryService;

    public function handle(array $data): void
    {
        $this->deliveryService->deliver((int) ($data['log_id'] ?? 0));
    }

    /** 回调不得抛出（库会把异常当成消费进程故障）：整段 catch，只记类名。 */
    public function onConsumeFailure(\Throwable $e, array $package): array
    {
        try {
            $package = parent::onConsumeFailure($e, $package);
            // 与父类同一判定：库在回调之后才 ++attempts，所以 attempts + 1 > max_attempts 即最后一次
            $attempts = (int) ($package['attempts'] ?? 0) + 1;
            $data = $package['data'] ?? [];
            $logId = is_array($data) ? (int) ($data['log_id'] ?? 0) : 0;
            if ($logId > 0 && $attempts > (int) ($package['max_attempts'] ?? 0)) {
                $this->deliveryService->markExhausted($logId, $e);
            }
        } catch (\Throwable $failure) {
            Log::error('消息发送失败回调处理异常', ['queue' => $this->queue, 'exception' => $failure::class]);
        }

        return $package;
    }
}
