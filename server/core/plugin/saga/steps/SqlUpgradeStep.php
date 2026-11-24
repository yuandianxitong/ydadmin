<?php

declare(strict_types=1);

namespace core\plugin\saga\steps;

use core\plugin\PluginSqlRunner;
use core\saga\SagaContext;
use support\Log;

/**
 * upgrade saga 第 5 步：跑 (from, to] 区间内尚未执行过的 database/updates/vX.Y.Z.sql 增量。
 *
 * 不包事务：MySQL 的 DDL 会隐式提交，外层 transaction() 收尾时再 commit 会抛“no active transaction”。
 * 补偿按逆序跑 updates/down/ 脚本；没有 down 脚本的增量退不回去，要通过 rollback_notes 让运维看见。
 */
final class SqlUpgradeStep extends AbstractPluginSagaStep
{
    public function __construct(array $saga, private readonly PluginSqlRunner $sqlRunner)
    {
        parent::__construct($saga);
    }

    public function name(): string
    {
        return 'SqlUpgrade';
    }

    /**
     * $ran 用引用出参：第 N 份抛出时前 N-1 份已提交，返回值拿不到，必须立刻写进 ctx 的 ran_migrations。
     * 抛出路径上自己先回退一次：SagaRunner 不补偿抛出的那一步，且此时 ran_migrations 还没落库。
     */
    public function execute(SagaContext $ctx): void
    {
        $ran = [];
        try {
            $this->sqlRunner->upgrade(
                $this->pluginDir(),
                $this->pluginId(),
                $this->code(),
                (string) $ctx->get('from_version', ''),
                (string) $ctx->get('to_version', ''),
                $ran
            );
        } catch (\Throwable $e) {
            $ctx->set('ran_migrations', $ran);
            $this->revert($ctx);
            throw $e;
        }
        $ctx->set('ran_migrations', $ran);
    }

    /**
     * 逆序回退本次真正执行过的增量。
     *
     * down 脚本在新版源码树里；本步补偿排在删新版目录、换回旧版之前，此刻还读得到。
     */
    public function compensate(SagaContext $ctx): void
    {
        $this->revert($ctx);
    }

    /**
     * 回退 + 留痕；幂等（回退掉的从 `ran_migrations` 里划掉，重复触发时是空
     * 集合），可被 execute() 的失败路径与 compensate() 各调一次。
     */
    private function revert(SagaContext $ctx): void
    {
        $ran = array_map('strval', (array) $ctx->get('ran_migrations', []));
        if ($ran === []) {
            return;
        }

        $reverted = $this->sqlRunner->down($this->pluginDir(), $this->pluginId(), $this->code(), $ran);
        $stuck = array_values(array_diff($ran, $reverted));
        $ctx->set('ran_migrations', $stuck);

        if ($stuck === []) {
            return;
        }

        // 退不回来的增量必须让运维看见，经 rollback_notes 由 runner 统一写进错误信息。
        // plugin_migrations.plugin_version 记的是 $toVersion，失败时排查以 plugins.version 为准。
        $ctx->push(
            'rollback_notes',
            ' | 本次已执行并提交的数据库增量（缺 down 脚本或回退失败，DDL 不可回滚，变更已永久留在库里）：'
                . implode(', ', $stuck)
        );
        Log::warning(sprintf(
            '[plugin-sql] 插件 %s 升级回滚：以下增量未能退回，变更留在库里：%s',
            $this->code(),
            implode(', ', $stuck)
        ));
    }
}
