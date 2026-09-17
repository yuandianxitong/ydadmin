<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\repository\message\MessageLogRepository;
use app\repository\message\MessageTemplateRepository;
use app\repository\message\UserNotificationReadRepository;
use app\repository\message\UserNotificationRepository;
use core\base\Repository;
use ReflectionProperty;
use tests\TestCase;

/**
 * 红线（M6b spec §2.5）：message_templates / message_logs / user_notifications / user_notification_reads
 * 不受数据权限约束，且是**不声明** `$dataScoped`（沿用基类 core\base\Repository 的默认值 false），与 Test22、Test26 同理。
 *
 * 模板与日志是全局运营数据，没有 created_by 与部门列；站内信由 C 端按 user_id 显式过滤，
 * 投递在队列进程里跑、没有管理员上下文。一旦声明 `$dataScoped`，管理端列表会按不存在的列过滤、
 * 消费者会查不到日志行；用反射钉住「没有重新声明」，比只检查「当前值是 false」更严格。
 */
final class Test35_MessageRepositoriesNotDataScopedTest extends TestCase
{
    public function test_message_template_repository_does_not_redeclare_data_scoped(): void
    {
        $this->assertNotDataScoped(MessageTemplateRepository::class);
    }

    public function test_message_log_repository_does_not_redeclare_data_scoped(): void
    {
        $this->assertNotDataScoped(MessageLogRepository::class);
    }

    public function test_user_notification_repository_does_not_redeclare_data_scoped(): void
    {
        $this->assertNotDataScoped(UserNotificationRepository::class);
    }

    public function test_user_notification_read_repository_does_not_redeclare_data_scoped(): void
    {
        $this->assertNotDataScoped(UserNotificationReadRepository::class);
    }

    /** @param class-string $class */
    private function assertNotDataScoped(string $class): void
    {
        $property = new ReflectionProperty($class, 'dataScoped');
        $this->assertSame(
            Repository::class,
            $property->getDeclaringClass()->getName(),
            "{$class} 不应重新声明 \$dataScoped：四张消息表没有 created_by 也没有部门列（M6b spec §2.5）"
        );
        $this->assertFalse($property->getDefaultValue(), '基类默认值应为 false');
    }
}
