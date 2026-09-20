<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\repository\wechat\WechatAutoReplyRepository;
use core\base\Repository;
use ReflectionProperty;
use tests\TestCase;

/**
 * 红线（M6c spec §4.1）：wechat_auto_replies 不受数据权限约束，且是**不声明** `$dataScoped`
 * （沿用基类 core\base\Repository 的默认值 false），与 Test22、Test26、Test35 同理。
 *
 * 自动回复是全局运营数据，表没有 created_by 与部门列；serve 回调在公开请求里匹配规则，没有管理员
 * 上下文。一旦声明 `$dataScoped`，管理端列表会按不存在的列过滤、回调进程会查不到启用规则；用反射
 * 钉住「没有重新声明」，比只检查「当前值是 false」更严格。
 */
final class Test39_AutoReplyRepositoryNotDataScopedTest extends TestCase
{
    public function test_auto_reply_repository_does_not_redeclare_data_scoped(): void
    {
        $property = new ReflectionProperty(WechatAutoReplyRepository::class, 'dataScoped');
        $this->assertSame(
            Repository::class,
            $property->getDeclaringClass()->getName(),
            'WechatAutoReplyRepository 不应重新声明 $dataScoped：wechat_auto_replies 没有 created_by 也没有部门列（M6c spec §4.1）'
        );
        $this->assertFalse($property->getDefaultValue(), '基类默认值应为 false');
    }
}
