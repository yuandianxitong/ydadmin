<?php

declare(strict_types=1);

namespace tests\fixtures\Queue;

use app\queue\ConsumerBase;
use core\context\RequestContext;

/**
 * 继承真实 ConsumerBase 的夹具消费者（队列名 fixture-base）。
 * - 记录每次 handle() 收到的数据，以及进入 handle() 时看到的操作人（用来证明上一个任务的上下文没有串过来）；
 * - 数据带 acting_user 时在任务里设置操作人；带 fail 真值时抛异常。
 */
final class FixtureBaseConsumer extends ConsumerBase
{
    public string $queue = 'fixture-base';

    /** @var list<array<string, mixed>> */
    public static array $handled = [];

    public static int $actingUserSeen = -1;

    public function handle(array $data): void
    {
        self::$handled[] = $data;
        self::$actingUserSeen = RequestContext::actingUser();
        if (isset($data['acting_user'])) {
            RequestContext::setActingUser((int) $data['acting_user']);
        }
        if (!empty($data['fail'])) {
            throw new \RuntimeException('fixture failure');
        }
    }
}
