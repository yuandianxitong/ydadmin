<?php
declare(strict_types=1);

namespace app\command;

use core\database\SqlRunner;
use PDO;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 框架数据库升级命令
 *
 * 按 database/updates/vX.Y.Z/ 的版本目录，依次执行尚未应用的增量脚本，
 * 已应用版本记录在 system_upgrades 表中，保证幂等、可断点续跑。
 *
 * 这是框架「发行版升级」的唯一数据库机制（不再使用 think-migration）。
 */
class YdUpdateCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('yd:update')
            ->setDescription('执行框架数据库增量升级（database/updates/vX.Y.Z）')
            ->addOption('dry-run', null, Option::VALUE_NONE, '仅列出将执行的版本，不实际执行')
            ->addOption('baseline', null, Option::VALUE_OPTIONAL, '首次使用时标记基线：将 <= 该版本的升级全部记为已应用（老用户升级前设置为当前数据库版本；全空库可用 0）');
    }

    protected function execute(Input $input, Output $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $baseline = $input->getOption('baseline');

        $prefix = (string) config('database.connections.mysql.prefix');
        $codeVersion = (string) config('version.version');
        $output->writeln("<info>框架代码版本：{$codeVersion}，数据表前缀：" . ($prefix === '' ? '(无)' : $prefix) . '</info>');

        try {
            $pdo = $this->createPdo();
        } catch (\Throwable $e) {
            $output->writeln('<error>数据库连接失败：' . $e->getMessage() . '</error>');
            return 1;
        }

        $runner = new SqlRunner($pdo, $prefix);
        $upgradeTable = '`' . $prefix . 'system_upgrades`';

        $tableExisted = $this->tableExists($pdo, $prefix . 'system_upgrades');
        // dry-run 不产生任何写操作（不建表、不写基线、不执行脚本）
        if (!$dryRun) {
            $this->ensureUpgradeTable($pdo, $upgradeTable);
        }

        $applied = $tableExisted ? $this->fetchAppliedVersions($pdo, $upgradeTable) : [];
        $available = $this->scanUpdateVersions();

        if (empty($available)) {
            $output->writeln('<comment>未发现任何升级脚本（database/updates 为空）。</comment>');
            return 0;
        }

        // 无任何已应用记录 = 首次使用（老用户/空表），需确立基线，避免对老库重复执行历史脚本。
        // 全新安装由安装程序 seed 基线，applied 不会为空，因此不会进入此分支。
        if (empty($applied)) {
            if ($baseline === null) {
                $output->writeln('<error>检测到首次使用升级系统，但无法确定数据库当前版本。</error>');
                $output->writeln('<comment>请指定基线：老用户用当前数据库版本，例如 php think yd:update --baseline=' . $codeVersion . '</comment>');
                $output->writeln('<comment>若为全新空库需从头执行全部脚本，可用 php think yd:update --baseline=0</comment>');
                return 1;
            }
            $baseline = (string) $baseline;
            $seeded = [];
            foreach ($available as $version) {
                if ($this->versionLte($version, $baseline)) {
                    if (!$dryRun) {
                        $this->recordVersion($pdo, $upgradeTable, $version);
                    }
                    $seeded[] = $version;
                    $applied[$version] = true;
                }
            }
            $label = $dryRun ? '将确立基线' : '已确立基线';
            $output->writeln('<info>' . $label . ' ' . $baseline . '，标记为已应用：' . (empty($seeded) ? '(无)' : implode(', ', $seeded)) . '</info>');
        }

        $pending = [];
        foreach ($available as $version) {
            if (!isset($applied[$version])) {
                $pending[] = $version;
            }
        }
        usort($pending, static fn($a, $b) => version_compare($a, $b));

        if (empty($pending)) {
            $output->writeln('<info>数据库已是最新，无需升级。</info>');
            return 0;
        }

        $output->writeln('<info>待执行版本（' . count($pending) . '）：' . implode(', ', $pending) . '</info>');

        if ($dryRun) {
            foreach ($pending as $version) {
                $files = $this->versionFiles($version);
                $parts = [];
                if ($files['sql'] !== null) {
                    $parts[] = 'update.sql';
                }
                if ($files['php'] !== null) {
                    $parts[] = 'update.php';
                }
                $output->writeln('  - ' . $version . '：' . (empty($parts) ? '(空目录)' : implode(' + ', $parts)));
            }
            $output->writeln('<comment>dry-run 模式，未执行任何 SQL。</comment>');
            return 0;
        }

        foreach ($pending as $version) {
            $files = $this->versionFiles($version);
            $output->writeln("<info>→ 执行 {$version} ...</info>");
            try {
                if ($files['sql'] !== null) {
                    $runner->runFile($files['sql']);
                }
                if ($files['php'] !== null) {
                    $hook = require $files['php'];
                    if (is_callable($hook)) {
                        $hook($pdo, $prefix);
                    }
                }
                $this->recordVersion($pdo, $upgradeTable, $version);
                $output->writeln("<info>  ✓ {$version} 完成</info>");
            } catch (\Throwable $e) {
                $output->writeln("<error>  ✗ {$version} 失败：" . $e->getMessage() . '</error>');
                $output->writeln('<comment>已中断。修复后重跑 php think yd:update 将从该版本继续（前面成功的版本不会重复执行）。</comment>');
                return 1;
            }
        }

        // 清缓存，确保新结构/配置立即生效
        $this->clearRuntimeCache();

        $latest = end($pending);
        $output->writeln("<info>升级完成，数据库已应用至 {$latest}。</info>");
        if (version_compare($latest, $codeVersion, '>')) {
            $output->writeln('<comment>提示：config/version.php 当前为 ' . $codeVersion . '，可同步更新为 ' . $latest . '。</comment>');
        }

        return 0;
    }

    private function createPdo(): PDO
    {
        $conf = (array) config('database.connections.mysql');
        $host = (string) ($conf['hostname'] ?? '127.0.0.1');
        $port = (string) ($conf['hostport'] ?? '3306');
        $db = (string) ($conf['database'] ?? '');
        $user = (string) ($conf['username'] ?? 'root');
        $pass = (string) ($conf['password'] ?? '');
        $charset = (string) ($conf['charset'] ?? 'utf8mb4');

        $dsn = "mysql:host={$host};dbname={$db};port={$port};charset={$charset}";
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function ensureUpgradeTable(PDO $pdo, string $upgradeTable): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS {$upgradeTable} (\n"
            . "  `id` bigint unsigned NOT NULL AUTO_INCREMENT,\n"
            . "  `version` varchar(20) NOT NULL COMMENT '已应用的版本号',\n"
            . "  `applied_at` datetime NOT NULL COMMENT '应用时间',\n"
            . "  PRIMARY KEY (`id`),\n"
            . "  UNIQUE KEY `uk_version` (`version`)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='框架数据库升级记录'"
        );
    }

    /**
     * @return array<string,bool> version => true
     */
    private function fetchAppliedVersions(PDO $pdo, string $upgradeTable): array
    {
        $rows = $pdo->query("SELECT version FROM {$upgradeTable}")->fetchAll(PDO::FETCH_COLUMN);
        $map = [];
        foreach ($rows as $v) {
            $map[(string) $v] = true;
        }
        return $map;
    }

    private function recordVersion(PDO $pdo, string $upgradeTable, string $version): void
    {
        $stmt = $pdo->prepare("INSERT IGNORE INTO {$upgradeTable} (`version`, `applied_at`) VALUES (?, ?)");
        $stmt->execute([$version, date('Y-m-d H:i:s')]);
    }

    /**
     * 扫描 database/updates 下所有 vX.Y.Z 版本目录
     *
     * @return array<int,string> 版本号（去掉 v 前缀）
     */
    private function scanUpdateVersions(): array
    {
        $dir = $this->updatesDir();
        if (!is_dir($dir)) {
            return [];
        }
        $versions = [];
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (!is_dir($dir . '/' . $entry)) {
                continue;
            }
            if (preg_match('/^v?(\d+\.\d+\.\d+)$/', $entry, $m)) {
                $versions[] = $m[1];
            }
        }
        usort($versions, static fn($a, $b) => version_compare($a, $b));
        return $versions;
    }

    /**
     * @return array{sql:?string,php:?string}
     */
    private function versionFiles(string $version): array
    {
        $base = $this->updatesDir() . '/v' . $version;
        $sql = $base . '/update.sql';
        $php = $base . '/update.php';
        return [
            'sql' => is_file($sql) ? $sql : null,
            'php' => is_file($php) ? $php : null,
        ];
    }

    private function updatesDir(): string
    {
        return rtrim(root_path(), '/\\') . '/database/updates';
    }

    private function tableExists(PDO $pdo, string $tableName): bool
    {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$tableName]);
        return (bool) $stmt->fetchColumn();
    }

    private function versionLte(string $a, string $b): bool
    {
        return version_compare($a, $b, '<=');
    }

    private function clearRuntimeCache(): void
    {
        $dirs = [runtime_path() . 'cache', runtime_path() . 'temp'];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            foreach (scandir($dir) ?: [] as $item) {
                if ($item === '.' || $item === '..' || $item === 'index.php') {
                    continue;
                }
                $path = rtrim($dir, '/\\') . '/' . $item;
                $this->deletePath($path);
            }
        }
    }

    private function deletePath(string $path): void
    {
        if (is_dir($path)) {
            foreach (scandir($path) ?: [] as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $this->deletePath(rtrim($path, '/\\') . '/' . $item);
            }
            @rmdir($path);
            return;
        }
        @unlink($path);
    }
}
