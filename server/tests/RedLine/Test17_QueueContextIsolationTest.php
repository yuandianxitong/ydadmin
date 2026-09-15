<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\queue\ConsumerBase;
use app\repository\system\AdminOperationLogRepository;
use core\context\RequestContext;
use core\datascope\DataScope;
use core\queue\QueueDispatcher;
use support\Container;
use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\ConfigOverride;

/**
 * 红线探针消费者。handle() 记下进入时的操作人；data.impersonate 非空时把操作人改成它（模拟某个任务
 * 在执行中设置了身份）；data.log_id 非空时经受数据权限约束的仓储查这条操作日志，记下是否可见。
 * 刻意放在红线测试文件里：它必须继承真实的 ConsumerBase，且只服务这一条红线。
 */
final class ContextProbeConsumer extends ConsumerBase
{
    public string $queue = 'rl-context-probe';

    /** @var list<array{acting_before: int, acting_after: int, log_visible: bool|null}> */
    public static array $seen = [];

    public function handle(array $data): void
    {
        $before = RequestContext::actingUser();
        if (!empty($data['impersonate'])) {
            RequestContext::setActingUser((int) $data['impersonate']);
        }
        $visible = isset($data['log_id'])
            ? (new AdminOperationLogRepository())->find((int) $data['log_id']) !== null
            : null;

        self::$seen[] = ['acting_before' => $before, 'acting_after' => RequestContext::actingUser(), 'log_visible' => $visible];
    }
}

/**
 * 红线（spec §5「跨进程约定」、§10、M1 延续清单）：队列消费进程不像 HTTP 请求那样按次重置 Context。
 * ConsumerBase::consume() 必须在 finally 里 Context::destroy()，否则上一个任务设置的操作人与数据范围快照
 * 会串到下一个任务——一个「仅本人」范围的身份可能让后续系统任务看不到数据，更糟的是反过来让本该受限的
 * 查询以宽身份执行。同时 sync 驱动（测试与请求内投递）调 handle() 而非 consume()，不得清掉调用方的 Context。
 */
final class Test17_QueueContextIsolationTest extends ApiTestCase
{
    use ConfigOverride;

    protected function setUp(): void
    {
        parent::setUp();
        ContextProbeConsumer::$seen = [];
    }

    protected function tearDown(): void
    {
        ContextProbeConsumer::$seen = [];
        $this->restoreConfig();
        parent::tearDown();
    }

    public function test_consecutive_jobs_do_not_share_acting_user_or_data_scope(): void
    {
        $owner = $this->actingAsAdmin();
        $narrow = $this->actingAsAdmin(['system.log.operation'], [], ['data_scope' => DataScope::SELF]);
        $now = date('Y-m-d H:i:s');
        // 归属 owner 的日志：「仅本人」的 narrow 看不到；无上下文（队列）时不过滤、可见。
        // trackAdmin 会在 tearDown 按 admin_id 清掉 admin_operation_logs，无需另行登记。
        $logId = (int) Db::table('admin_operation_logs')->insertGetId([
            'admin_id'       => $owner->id,
            'username'       => $owner->username,
            'method'         => 'POST',
            'path'           => '/redline/queue-context',
            'operation_time' => $now,
            'created_at'     => $now,
        ]);

        /** @var ContextProbeConsumer $consumer */
        $consumer = Container::make(ContextProbeConsumer::class, []);
        $consumer->consume(['impersonate' => $narrow->id, 'log_id' => $logId]);
        $consumer->consume(['log_id' => $logId]);

        $this->assertSame(
            [
                ['acting_before' => 0, 'acting_after' => $narrow->id, 'log_visible' => false],
                ['acting_before' => 0, 'acting_after' => 0, 'log_visible' => true],
            ],
            ContextProbeConsumer::$seen,
            '第一个任务：以 narrow 身份执行，数据权限生效看不到 owner 的日志；第二个任务：身份与数据范围快照都必须已被清空'
        );
        $this->assertSame(0, RequestContext::actingUser(), 'consume() 返回后 Context 必须已销毁');
        $this->assertNull(DataScope::current(), '无操作人即无数据范围：队列任务不过滤（总 spec §5.3）');
    }

    public function test_sync_dispatch_runs_in_the_callers_context_and_leaves_it_intact(): void
    {
        $this->overrideConfig('queue.queues.rl-context-probe', ['consumer' => ContextProbeConsumer::class, 'max_attempts' => 0]);
        RequestContext::setActingUser(42);

        Container::get(QueueDispatcher::class)->dispatch('rl-context-probe', []);

        $this->assertSame(42, ContextProbeConsumer::$seen[0]['acting_before'] ?? null, 'sync 驱动在调用方的上下文里执行（设计决定 7）');
        $this->assertSame(42, RequestContext::actingUser(), 'sync 驱动调 handle() 不调 consume()：不得清掉调用方的 Context（设计决定 3）');
    }
}
