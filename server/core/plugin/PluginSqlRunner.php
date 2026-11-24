<?php

declare(strict_types=1);

namespace core\plugin;

use core\exception\BusinessException;
use core\plugin\contracts\PluginHostProfile;
use core\plugin\contracts\PluginMigrationStore;
use support\Db;
use support\Log;

/**
 * 插件 SQL 执行器。
 *
 * 目录约定 `<pluginDir>/database/`：
 *   install.sql   全新安装（始终反映最新完整结构）
 *   uninstall.sql 清理数据（purge / saga 补偿）
 *   testdata.sql  演示数据（仅 DML，`{TENANT_ID}` 占位）
 *   updates/vX.Y.Z.sql 增量升级：install() 只把它们登记为已应用、不执行，真正执行是 upgrade() 的事
 *   updates/down/vX.Y.Z.sql 增量的回退脚本，可选
 *
 * 执行记录经 PluginMigrationStore 存取（容器里绑定到宿主实现）：已成功的文件不重跑，hash 防篡改
 * 由构造时注入的 PluginHostProfile::strictMigrationHash() 决定。
 * 构造参数不能加 `= null` 默认值：php-di 自动装配会跳过可选参数，绑定就落空了。
 */
class PluginSqlRunner
{
    private PluginMigrationStore $logRepo;

    private PluginHostProfile $profile;

    public function __construct(PluginMigrationStore $logRepo, PluginHostProfile $profile)
    {
        $this->logRepo = $logRepo;
        $this->profile = $profile;
    }

    /** @return string[] 本次真正执行的文件相对路径 */
    public function install(string $pluginDir, int $pluginId, string $pluginCode, ?string $pluginVersion = null): array
    {
        $this->verifyHashes($pluginDir, $pluginId);

        $files = $this->listSqlFiles($pluginDir);
        $applied = $this->logRepo->successfulUpNames($pluginId);

        $ran = [];
        foreach ($files as $rel => $abs) {
            if (in_array($rel, $applied, true)) {
                continue;
            }
            // 全新安装只跑 install.sql；updates/ 是历史增量，新装环境结构已含在
            // install.sql 里，直接标记为已应用（backfill），避免将来跑 upgrade()
            // 时重复执行。
            if ($rel === 'database/install.sql') {
                $this->runFile($abs, $rel, $pluginId, $pluginCode, $pluginVersion);
                $ran[] = $rel;
            } else {
                $hash = (string) hash_file('sha256', $abs);
                $this->logRepo->markSuccess($pluginId, $pluginCode, $rel, $hash, 'up', $pluginVersion, 0);
            }
        }
        return $ran;
    }

    /**
     * 执行 `(fromVersion, toVersion]` 区间内、尚未成功执行过的 `updates/vX.Y.Z.sql`
     * 增量（按版本升序），`install.sql` 恒不重跑。落库时 plugin_version 记 $toVersion，不是文件自身的版本号。
     *
     * $ran 用引用出参：第 N 份抛出时前 N-1 份已提交（DDL 不可回滚），返回值在抛出时拿不到。
     *
     * @param  string[] $ran 出参：本次真正执行成功的文件相对路径（按版本升序）
     * @return string[] 同 $ran（成功返回时使用）
     */
    public function upgrade(string $pluginDir, int $pluginId, string $pluginCode, string $fromVersion, string $toVersion, array &$ran = []): array
    {
        $ran = [];

        $this->verifyHashes($pluginDir, $pluginId);

        $files = $this->listSqlFiles($pluginDir);
        $applied = $this->logRepo->successfulUpNames($pluginId);

        foreach ($files as $rel => $abs) {
            if ($rel === 'database/install.sql' || in_array($rel, $applied, true)) {
                continue;
            }
            $ver = self::versionOf($abs);
            if (version_compare($ver, $fromVersion, '<=') || version_compare($ver, $toVersion, '>')) {
                continue;
            }
            $this->runFile($abs, $rel, $pluginId, $pluginCode, $toVersion);
            $ran[] = $rel;
        }
        return $ran;
    }

    /**
     * 把 {@see upgrade()} 本次真正执行过的增量逆序回退（后面的 down 往往依赖前面的增量建出的东西还在）。
     *
     * - down 脚本放在 updates/down/ 子目录，不能命名成 updates/vX.Y.Z.down.sql：那会被 listSqlFiles() 当成 up 迁移扫进来。
     * - 缺 down 脚本或 down 执行失败都只记日志、继续回退其余增量，且不计入返回值；
     *   调用方用 array_diff($ran, $reverted) 算出永久留在库里的变更。补偿期不抛出。
     * - down 成功后先删掉该增量的 up 成功行，否则重跑升级会跳过它；再以 down 文件自己的路径和 hash 记一条审计行。
     *
     * @param  string[] $ranMigrations `upgrade()` 回填的 up 相对路径（按版本升序）
     * @return string[] 实际回退掉的 up 相对路径（按执行顺序，即版本降序）
     */
    public function down(string $pluginDir, int $pluginId, string $pluginCode, array $ranMigrations): array
    {
        $base = rtrim($pluginDir, '/');
        $reverted = [];

        foreach (array_reverse(array_values($ranMigrations)) as $upRel) {
            $upRel = (string) $upRel;
            $downRel = self::downPathFor($upRel);
            if ($downRel === null) {
                Log::warning("[plugin-sql] {$pluginCode} {$upRel} 不是可识别的增量路径，跳过回退");
                continue;
            }
            $abs = $base . '/' . $downRel;
            if (!is_file($abs)) {
                Log::warning("[plugin-sql] {$pluginCode} {$upRel} 缺 down 脚本（{$downRel}），跳过回退");
                continue;
            }

            // 算不出 hash 不抛：这是 down 审计行的字段，没有任何校验读它，为它打断补偿链不值得。
            $hash = @hash_file('sha256', $abs);
            if ($hash === false) {
                Log::warning("[plugin-sql] {$pluginCode} {$downRel} 无法计算 sha256，本次回退的审计行 file_hash 将为空");
                $hash = '';
            }
            $t0 = (int) (microtime(true) * 1000);
            try {
                foreach (SqlScript::split((string) file_get_contents($abs)) as $stmt) {
                    Db::statement($stmt);
                }
            } catch (\Throwable $e) {
                $duration = ((int) (microtime(true) * 1000)) - $t0;
                $this->logRepo->markFailed(
                    $pluginId,
                    $pluginCode,
                    $downRel,
                    $hash,
                    'down',
                    null,
                    $duration,
                    $e->getMessage()
                );
                Log::error("[plugin-sql] {$pluginCode} {$downRel} 回退失败，该增量的变更仍留在库里：" . $e->getMessage());
                continue;
            }

            $duration = ((int) (microtime(true) * 1000)) - $t0;
            // 删到 0 行说明台账已被别处改过，将来重跑升级会重复执行这份增量。回退本身已成功，不抛，但必须记日志。
            if ($this->logRepo->deleteSuccessfulUp($pluginId, $upRel) === 0) {
                Log::warning(
                    "[plugin-sql] {$pluginCode} {$upRel} 回退成功，但没有删到对应的 up 成功行"
                    . '（台账已被其它路径改动过），将来重跑升级可能重复执行这份增量'
                );
            }
            $this->logRepo->markSuccess(
                $pluginId,
                $pluginCode,
                $downRel,
                $hash,
                'down',
                null,
                $duration
            );
            $reverted[] = $upRel;
        }

        return $reverted;
    }

    /**
     * up 增量的相对路径 → 对应 down 脚本的相对路径；不是可识别的增量路径时返回 null。
     * public static：调用方判断缺脚本还是跑失败时必须用同一份路径约定。
     */
    public static function downPathFor(string $upRelPath): ?string
    {
        if (!preg_match('#^database/updates/(v\d+\.\d+\.\d+\.sql)$#', $upRelPath, $m)) {
            return null;
        }

        return 'database/updates/down/' . $m[1];
    }

    public function hasTestdata(string $pluginDir): bool
    {
        return is_file(rtrim($pluginDir, '/') . '/database/testdata.sql');
    }

    /**
     * 导入演示数据：`{TENANT_ID}` → 租户 ID；仅 DML（INSERT/UPDATE）；事务包裹——
     * 失败整体回滚，重试不会产生重复行。
     *
     * @return int 执行语句数
     */
    public function importTestdata(string $pluginDir, int $scopeId): int
    {
        $file = rtrim($pluginDir, '/') . '/database/testdata.sql';
        if (!is_file($file)) {
            throw new BusinessException('插件不包含演示数据（database/testdata.sql）', 404);
        }

        $sql = str_replace('{TENANT_ID}', (string) $scopeId, (string) file_get_contents($file));
        $stmts = SqlScript::split($sql);
        foreach ($stmts as $stmt) {
            if (!preg_match('/^(INSERT|UPDATE)\b/i', $stmt)) {
                throw new BusinessException('testdata.sql 仅允许 INSERT/UPDATE 语句', 422);
            }
        }

        Db::transaction(static function () use ($stmts) {
            foreach ($stmts as $stmt) {
                Db::statement($stmt);
            }
        });
        return count($stmts);
    }

    /** 执行 uninstall.sql（如有）并清空状态行。@return bool 是否执行了 uninstall.sql */
    public function uninstallData(string $pluginDir, int $pluginId, string $pluginCode, ?string $pluginVersion = null): bool
    {
        $file = rtrim($pluginDir, '/') . '/database/uninstall.sql';
        $ran = false;
        if (is_file($file)) {
            foreach (SqlScript::split((string) file_get_contents($file)) as $stmt) {
                Db::statement($stmt);
            }
            $ran = true;
        }
        $this->logRepo->purgeForPlugin($pluginId);
        return $ran;
    }

    /**
     * 校验 SQL 文件 hash 是否与执行记录一致，检测「已执行文件被篡改」。
     *
     * 排除 `database/install.sql`：它随每个版本合法演进为最新完整结构，且成功后永不重跑，
     * 纳入校验只会让每次正常升级都被判成 hash 漂移。
     *
     * @return array<int, array{name: string, recorded: string, current: string}>
     */
    public function verifyHashes(string $pluginDir, int $pluginId): array
    {
        $files = $this->listSqlFiles($pluginDir);
        $recorded = $this->logRepo->upHashes($pluginId);

        $mismatch = [];
        foreach ($files as $rel => $abs) {
            if ($rel === 'database/install.sql' || !isset($recorded[$rel])) {
                continue;
            }
            $current = (string) hash_file('sha256', $abs);
            if ($current !== $recorded[$rel]) {
                $mismatch[] = ['name' => $rel, 'recorded' => $recorded[$rel], 'current' => $current];
            }
        }

        if (!empty($mismatch)) {
            $names = implode(', ', array_column($mismatch, 'name'));
            $msg = "plugin {$pluginId} SQL 文件 hash 漂移：{$names}";
            if ($this->profile->strictMigrationHash()) {
                throw new BusinessException($msg . '（已开启 strict 模式，拒绝继续）', 409);
            }
            Log::error('[PluginSqlRunner] ' . $msg);
        }
        return $mismatch;
    }

    /** @return string[] 未执行的文件相对路径 */
    public function pendingUp(string $pluginDir, int $pluginId): array
    {
        $files = array_keys($this->listSqlFiles($pluginDir));
        $applied = $this->logRepo->successfulUpNames($pluginId);
        return array_values(array_diff($files, $applied));
    }

    /** 单文件执行：分句 → 逐句 execute → 记状态（成功/失败）。 */
    private function runFile(string $abs, string $rel, int $pluginId, string $pluginCode, ?string $pluginVersion): void
    {
        $hash = (string) hash_file('sha256', $abs);
        $t0 = (int) (microtime(true) * 1000);
        try {
            foreach (SqlScript::split((string) file_get_contents($abs)) as $stmt) {
                Db::statement($stmt);
            }
            $duration = ((int) (microtime(true) * 1000)) - $t0;
            $this->logRepo->markSuccess($pluginId, $pluginCode, $rel, $hash, 'up', $pluginVersion, $duration);
        } catch (\Throwable $e) {
            $duration = ((int) (microtime(true) * 1000)) - $t0;
            // 失败必须既落库又重抛：只留痕不抛会静默半成功，只抛不留痕事后无从排查。
            $this->logRepo->markFailed($pluginId, $pluginCode, $rel, $hash, 'up', $pluginVersion, $duration, $e->getMessage());
            throw $e;
        }
    }

    /**
     * @return array<string, string> 相对路径 => 绝对路径；install.sql 恒排首位，
     *                                updates/ 按 version_compare 升序
     */
    private function listSqlFiles(string $pluginDir): array
    {
        $base = rtrim($pluginDir, '/') . '/database';
        $out = [];
        if (is_file($base . '/install.sql')) {
            $out['database/install.sql'] = $base . '/install.sql';
        }
        $updates = glob($base . '/updates/v*.sql') ?: [];
        usort($updates, static fn (string $a, string $b) => version_compare(self::versionOf($a), self::versionOf($b)));
        foreach ($updates as $file) {
            $out['database/updates/' . basename($file)] = $file;
        }
        return $out;
    }

    private static function versionOf(string $file): string
    {
        return preg_match('/^v(\d+\.\d+\.\d+)\.sql$/', basename($file), $m) ? $m[1] : '0.0.0';
    }
}
