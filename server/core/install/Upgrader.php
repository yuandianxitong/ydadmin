<?php

declare(strict_types=1);

namespace core\install;

use core\database\SqlScript;
use core\exception\BusinessException;

final class Upgrader
{
    private const TABLE_SQL = <<<'SQL'
CREATE TABLE `system_upgrades` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(32) NOT NULL COMMENT '已应用版本，如 2.0.0',
  `applied_at` datetime NOT NULL COMMENT '打标时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='框架升级记录'
SQL;

    public function __construct(private string $updatesDir)
    {
    }

    /**
     * @return array{stamped: list<string>, executed: list<string>, pending: list<string>}
     */
    /** 同一个库同时跑两次升级会把同一版的 SQL 执行两遍（唯一键只能在事后报错）。 */
    private const LOCK_NAME = 'ydadmin:upgrade';

    /**
     * @return array{stamped: list<string>, executed: list<string>, pending: list<string>}
     */
    public function run(\PDO $pdo, ?string $baseline, bool $dryRun): array
    {
        if (!$dryRun && !$this->acquireLock($pdo)) {
            throw new BusinessException(lang('install.upgrade_running'));
        }

        try {
            return $this->runLocked($pdo, $baseline, $dryRun);
        } finally {
            if (!$dryRun) {
                $pdo->query('SELECT RELEASE_LOCK(' . $pdo->quote(self::LOCK_NAME) . ')');
            }
        }
    }

    private function acquireLock(\PDO $pdo): bool
    {
        return (int) $pdo->query('SELECT GET_LOCK(' . $pdo->quote(self::LOCK_NAME) . ', 0)')->fetchColumn() === 1;
    }

    /**
     * @return array{stamped: list<string>, executed: list<string>, pending: list<string>}
     */
    private function runLocked(\PDO $pdo, ?string $baseline, bool $dryRun): array
    {
        $scanned = $this->scanVersions();
        $applied = $this->readApplied($pdo);

        if ($applied === [] && $baseline === null) {
            throw new BusinessException(lang('install.baseline_required'));
        }

        if ($applied === []) {
            $stamped = $this->versionsToStamp($scanned, (string) $baseline);
            if (!$dryRun) {
                $this->ensureTable($pdo);
                foreach ($stamped as $version) {
                    $this->insertVersion($pdo, $version);
                }
            }

            return [
                'stamped'  => $stamped,
                'executed' => [],
                'pending'  => $this->pendingVersions($scanned, $stamped),
            ];
        }

        $pending = $this->pendingVersions($scanned, $applied);
        if ($dryRun) {
            return [
                'stamped'  => [],
                'executed' => [],
                'pending'  => $pending,
            ];
        }

        $executed = [];
        foreach ($pending as $version) {
            $this->executeVersion($pdo, $scanned[$version]);
            $this->insertVersion($pdo, $version);
            $executed[] = $version;
        }

        return [
            'stamped'  => [],
            'executed' => $executed,
            'pending'  => [],
        ];
    }

    /** @return array<string, string> version => 目录绝对路径 */
    private function scanVersions(): array
    {
        if (!is_dir($this->updatesDir)) {
            return [];
        }

        $updatesReal = realpath($this->updatesDir);
        if ($updatesReal === false) {
            return [];
        }

        $found = [];
        foreach (scandir($updatesReal) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            if (preg_match('/^v(\d+\.\d+\.\d+)$/', $name, $match) !== 1) {
                continue;
            }
            $path = $updatesReal . DIRECTORY_SEPARATOR . $name;
            if (!is_dir($path)) {
                continue;
            }
            $versionReal = realpath($path);
            if ($versionReal === false || !str_starts_with($versionReal, $updatesReal . DIRECTORY_SEPARATOR)) {
                throw new BusinessException(lang('install.invalid_update_dir'));
            }
            $found[$match[1]] = $versionReal;
        }
        uksort($found, 'version_compare');

        return $found;
    }

    /** @return list<string> */
    private function readApplied(\PDO $pdo): array
    {
        if ($pdo->query("SHOW TABLES LIKE 'system_upgrades'")->fetch() === false) {
            return [];
        }

        /** @var list<string> $versions */
        $versions = $pdo->query('SELECT version FROM system_upgrades')->fetchAll(\PDO::FETCH_COLUMN);

        return $versions;
    }

    private function ensureTable(\PDO $pdo): void
    {
        if ($pdo->query("SHOW TABLES LIKE 'system_upgrades'")->fetch() !== false) {
            return;
        }
        $pdo->exec(self::TABLE_SQL);
    }

    private function insertVersion(\PDO $pdo, string $version): void
    {
        $stmt = $pdo->prepare('INSERT INTO system_upgrades (version, applied_at) VALUES (?, NOW())');
        $stmt->execute([$version]);
    }

    /**
     * @param array<string, string> $scanned
     * @return list<string>
     */
    private function versionsToStamp(array $scanned, string $baseline): array
    {
        $stamped = [$baseline => true];
        foreach (array_keys($scanned) as $version) {
            if (version_compare($version, $baseline) <= 0) {
                $stamped[$version] = true;
            }
        }
        $versions = array_keys($stamped);
        usort($versions, 'version_compare');

        return $versions;
    }

    /**
     * @param array<string, string> $scanned
     * @param list<string> $applied
     * @return list<string>
     */
    private function pendingVersions(array $scanned, array $applied): array
    {
        $pending = [];
        foreach (array_keys($scanned) as $version) {
            if (!in_array($version, $applied, true)) {
                $pending[] = $version;
            }
        }
        usort($pending, 'version_compare');

        return $pending;
    }

    private function executeVersion(\PDO $pdo, string $versionDir): void
    {
        $sqlFile = $this->resolveFile($versionDir, 'update.sql');
        if ($sqlFile !== null) {
            foreach (SqlScript::split((string) file_get_contents($sqlFile)) as $statement) {
                $pdo->exec($statement);
            }
        }

        $phpFile = $this->resolveFile($versionDir, 'update.php');
        if ($phpFile !== null) {
            $callback = require $phpFile;
            if (!$callback instanceof \Closure) {
                throw new BusinessException(lang('install.invalid_update_dir'));
            }
            $callback($pdo);
        }
    }

    private function resolveFile(string $versionDir, string $basename): ?string
    {
        $candidate = $versionDir . DIRECTORY_SEPARATOR . $basename;
        if (!is_link($candidate) && !is_file($candidate)) {
            return null;
        }

        $dirReal = realpath($versionDir);
        $fileReal = realpath($candidate);
        if ($dirReal === false || $fileReal === false || !is_file($fileReal)
            || basename($fileReal) !== $basename
            || !str_starts_with($fileReal, $dirReal . DIRECTORY_SEPARATOR)) {
            throw new BusinessException(lang('install.invalid_update_dir'));
        }

        return $fileReal;
    }
}
