<?php

declare(strict_types=1);

namespace core\cron;

use Cron\CronExpression;

/**
 * cron 表达式的三个判定（spec §5、§7、§8.1），纯函数、无状态。
 *
 * 只接受标准 5 段（分 时 日 月 周）。dragonmantank/cron-expression 自己接受 @hourly 之类的宏，
 * 这里先按空白切分、段数必须恰好为 5，宏与 6 段写法因此一律拒绝（spec §2 非目标：不支持秒级与宏）。
 * 时区取传入时间自身的时区（库在未显式传 $timeZone 时就是这么做的），调用方负责按 config/app.php 的
 * default_timezone 构造时间。
 */
final class CronSchedule
{
    public static function isValid(string $expression): bool
    {
        $parts = preg_split('/\s+/', trim($expression), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) !== 5) {
            return false;
        }

        return CronExpression::isValidExpression(implode(' ', $parts));
    }

    /** $minute 的秒数被忽略（库内部先把秒归零再比较）。表达式非法时返回 false。 */
    public static function isDue(string $expression, \DateTimeImmutable $minute): bool
    {
        if (!self::isValid($expression)) {
            return false;
        }

        return (new CronExpression(trim($expression)))->isDue($minute);
    }

    /**
     * 严格晚于 $now 的下一次到点时间（恰好在到点那一分钟时返回下一个周期），时区与 $now 相同。
     *
     * @throws \InvalidArgumentException 表达式不是合法的 5 段 cron
     */
    public static function nextRunAt(string $expression, \DateTimeImmutable $now): \DateTimeImmutable
    {
        if (!self::isValid($expression)) {
            throw new \InvalidArgumentException("不是合法的 5 段 cron 表达式：{$expression}");
        }

        return \DateTimeImmutable::createFromMutable((new CronExpression(trim($expression)))->getNextRunDate($now));
    }
}
