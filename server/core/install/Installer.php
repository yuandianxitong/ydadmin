<?php

declare(strict_types=1);

namespace core\install;

use core\contract\SuperAdminInitializer;
use core\database\DatabaseInstaller;
use core\database\SqlScript;
use core\exception\BusinessException;
use core\exception\ValidationException;
use Illuminate\Database\Capsule\Manager as Capsule;
use support\Redis;
use Webman\Config;
use Webman\Redis\RedisManager;

final class Installer
{
    public function __construct(
        private SuperAdminInitializer $admins,
        private string $installDir,
        private string $envPath,
        private string $envExamplePath,
        private string $lockPath,
        private ?string $storageRoot = null,
    ) {
        if ($this->storageRoot === null || $this->storageRoot === '') {
            $configuredRoot = (string) config('filesystem.disks.public.root');
            $this->storageRoot = $configuredRoot !== '' ? $configuredRoot : base_path('public/storage');
        }
    }

    public function isInstalled(): bool
    {
        if (is_file($this->lockPath)) {
            return true;
        }

        try {
            $connection = (array) config('database.connections.mysql');
            $database = (string) ($connection['database'] ?? '');
            if (preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
                return false;
            }
            $pdo = DatabaseInstaller::connect($connection);
            $pdo->exec("USE `{$database}`");
            if ($pdo->query("SHOW TABLES LIKE 'system_upgrades'")->fetch() === false) {
                return false;
            }

            return (int) $pdo->query('SELECT COUNT(*) FROM system_upgrades')->fetchColumn() > 0;
        } catch (\Throwable) {
            // 连不上、认证失败、权限不足时不能当「未安装」：lock 被部署清掉再赶上一次数据库抖动，
            // 未登录的安装向导就会重新开放，任何人都能把 .env 指向自己的库并在那边建超管。
            // 判据是 .env 里的 JWT 密钥——安装器写完才有，.env.example 里是空的，
            // 所以全新机器（还没填凭据、库同样连不上）仍然走得进向导。
            return $this->envCarriesInstalledSecrets();
        }
    }

    /** .env 里两个 JWT 密钥都非空 → 这台机器上跑完过一次安装。 */
    private function envCarriesInstalledSecrets(): bool
    {
        if (!is_file($this->envPath)) {
            return false;
        }

        $text = (string) file_get_contents($this->envPath);
        foreach (['JWT_ADMIN_SECRET', 'JWT_USER_SECRET'] as $key) {
            // 不用 \s：它包含换行。空的「JWT_ADMIN_SECRET =」会把下一行吞进来，
            // 复制 .env.example 时下一行不是空的，就会被当成已经写过密钥。
            if (preg_match('/^[ \t]*' . $key . '[ \t]*=[ \t]*(.*)$/m', $text, $m) !== 1) {
                return false;
            }
            $value = trim($m[1]);
            if (strlen($value) >= 2 && $value[0] === '"' && str_ends_with($value, '"')) {
                $value = substr($value, 1, -1);
            }
            if (trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $input */
    public function run(array $input): void
    {
        if ($this->isInstalled()) {
            throw new BusinessException(lang('install.already_installed'));
        }

        $importDemo = self::wantsDemo($input['import_demo'] ?? null);
        $this->assertInstallFilesWritable();
        if ($importDemo) {
            $this->assertStorageWritable();
        }
        $database = (string) ($input['db_name'] ?? '');
        if (preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
            throw new BusinessException('数据库名只允许字母、数字与下划线');
        }

        $this->testDatabase($input);
        $this->testRedis($input);

        $pdo = DatabaseInstaller::connect([
            'host'     => (string) $input['db_host'],
            'port'     => $input['db_port'],
            'username' => (string) $input['db_user'],
            'password' => (string) $input['db_password'],
        ]);

        // 两个人同时点「开始安装」时，后一个可能在前一个写 .env 之前就跑完自己的那份，
        // 最后 .env 里是谁的密钥说不准。拿不到锁就直接拒绝。
        if ((int) $pdo->query("SELECT GET_LOCK('ydadmin:install', 0)")->fetchColumn() !== 1) {
            throw new BusinessException(lang('install.install_running'));
        }

        try {
            $this->runLocked($pdo, $input, $database, $importDemo);
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('ydadmin:install')");
        }
    }

    /** @param array<string, mixed> $input */
    private function runLocked(\PDO $pdo, array $input, string $database, bool $importDemo): void
    {
        $exists = $pdo->query(
            'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ' . $pdo->quote($database)
        )->fetch();
        try {
            if ($exists === false) {
                $pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
            }
            $pdo->exec("USE `{$database}`");
        } catch (\Throwable $e) {
            throw new BusinessException(lang('install.database_create_failed'), 400, $e);
        }
        if ($pdo->query('SHOW TABLES')->fetchAll() !== []) {
            throw new BusinessException(lang('install.database_not_empty'));
        }

        try {
            foreach (['schema.sql', 'init.sql', 'regions.sql'] as $file) {
                foreach (SqlScript::split((string) file_get_contents($this->installDir . '/' . $file)) as $statement) {
                    $pdo->exec($statement);
                }
            }
        } catch (\Throwable $e) {
            throw new BusinessException(lang('install.sql_failed'), 400, $e);
        }

        if (array_key_exists('site_url', $input)) {
            $this->writeSiteUrl($pdo, self::normalizeSiteUrl((string) $input['site_url']));
        }

        if ($importDemo) {
            $this->importDemo($pdo, self::normalizeSiteUrl((string) ($input['site_url'] ?? '')));
        }

        do {
            $adminSecret = bin2hex(random_bytes(32));
            $userSecret = bin2hex(random_bytes(32));
        } while ($adminSecret === $userSecret);

        (new EnvFile())->merge($this->envPath, $this->envExamplePath, [
            'APP_DEBUG'         => 'false',
            'DB_HOST'           => (string) $input['db_host'],
            'DB_PORT'           => (string) $input['db_port'],
            'DB_NAME'           => $database,
            'DB_USER'           => (string) $input['db_user'],
            'DB_PASSWORD'       => (string) $input['db_password'],
            'DB_PREFIX'         => '',
            'REDIS_HOST'        => (string) $input['redis_host'],
            'REDIS_PORT'        => (string) $input['redis_port'],
            'REDIS_PASSWORD'    => (string) ($input['redis_password'] ?? ''),
            'REDIS_DB'          => (string) $input['redis_db'],
            'JWT_ADMIN_SECRET'  => $adminSecret,
            'JWT_USER_SECRET'   => $userSecret,
        ]);

        $this->injectMysqlConfig([
            'host'     => (string) $input['db_host'],
            'port'     => $input['db_port'],
            'database' => $database,
            'username' => (string) $input['db_user'],
            'password' => (string) $input['db_password'],
        ]);
        $this->injectRedisConfig([
            'host'     => (string) $input['redis_host'],
            'port'     => (int) $input['redis_port'],
            'password' => (string) ($input['redis_password'] ?? ''),
            'database' => (int) $input['redis_db'],
        ]);

        $this->admins->initSuperAdmin(
            (string) $input['username'],
            (string) $input['password'],
            isset($input['email']) ? (string) $input['email'] : null,
            isset($input['nickname']) ? (string) $input['nickname'] : null,
        );

        // 新装的库已经是 schema.sql 的最新形态，所以把「当前版本以及更早的升级目录」全部打成已应用：
        // 写死 2.0.0 的话，之后每个新装都挂着一堆待升级，第一个 ALTER TABLE 会砸在本来就有那列的新库上。
        (new Upgrader(dirname($this->installDir) . '/updates'))
            ->run($pdo, (string) config('version.version', '2.0.0'), false);
        if (@file_put_contents($this->lockPath, date('c')) === false) {
            throw new BusinessException(lang('install.lock_not_writable'));
        }
    }

    /** 灌库之前先确认 .env 和 install.lock 写得进去，避免写到一半才变成未捕获的 500。 */
    private function assertInstallFilesWritable(): void
    {
        if (!is_file($this->envExamplePath)) {
            throw new BusinessException(lang('install.env_not_writable'));
        }
        $envDir = dirname($this->envPath);
        $envWritable = is_file($this->envPath) ? is_writable($this->envPath) : (is_dir($envDir) && is_writable($envDir));
        if (!$envWritable) {
            throw new BusinessException(lang('install.env_not_writable'));
        }

        $lockDir = dirname($this->lockPath);
        if (!is_dir($lockDir) || !is_writable($lockDir)) {
            throw new BusinessException(lang('install.lock_not_writable'));
        }
    }

    public static function wantsDemo(mixed $value): bool
    {
        if (in_array($value, [null, '', false, 0, '0', 'false'], true)) {
            return false;
        }
        if (in_array($value, [true, 1, '1'], true)) {
            return true;
        }

        throw new ValidationException(['import_demo' => '演示数据开关无效']);
    }

    public static function normalizeSiteUrl(string $url): string
    {
        $url = rtrim($url, '/');

        return preg_match('#^https?://[A-Za-z0-9._\-]+(:\d{1,5})?$#', $url) === 1
            ? $url
            : 'http://localhost';
    }

    private function writeSiteUrl(\PDO $pdo, string $siteUrl): void
    {
        if ($pdo->query("SHOW TABLES LIKE 'system_configs'")->fetch() === false) {
            return;
        }
        $stmt = $pdo->prepare('UPDATE system_configs SET config_value = ? WHERE config_key = ?');
        $stmt->execute([$siteUrl, 'site_url']);
    }

    private function assertStorageWritable(): void
    {
        $storageRoot = (string) $this->storageRoot;
        if ((!is_dir($storageRoot) && !@mkdir($storageRoot, 0o755, true) && !is_dir($storageRoot))
            || !is_writable($storageRoot)
        ) {
            throw new BusinessException(lang('install.storage_not_writable'));
        }
    }

    private function importDemo(\PDO $pdo, string $siteUrl): void
    {
        $sqlFile = $this->installDir . '/demo.sql';
        $assets = $this->installDir . '/demo-assets';
        try {
            if (!is_file($sqlFile)) {
                throw new \RuntimeException('缺少 demo.sql');
            }
            $contents = @file_get_contents($sqlFile);
            if ($contents === false) {
                throw new \RuntimeException('无法读取 demo.sql');
            }
            $sql = str_replace('{{SITE_URL}}', $siteUrl, $contents);
            // demo.sql 保持 1.x 原始字节，仅在导入时重命名 articles 的创建人列。
            $position = strpos($sql, 'INSERT INTO `articles` ');
            if ($position !== false) {
                $lineLength = strcspn($sql, "\r\n", $position);
                $insertLine = substr($sql, $position, $lineLength);
                $sql = substr_replace(
                    $sql,
                    str_replace('`admin_id`', '`created_by`', $insertLine),
                    $position,
                    $lineLength
                );
            }
            foreach (SqlScript::split($sql) as $statement) {
                $pdo->exec($statement);
            }
            if (is_dir($assets)) {
                $this->copyMissing($assets, (string) $this->storageRoot);
            }
        } catch (\Throwable $e) {
            throw new BusinessException(lang('install.sql_failed'), 400, $e);
        }
    }

    private function copyMissing(string $assets, string $destination): void
    {
        $root = realpath($assets);
        if ($root === false) {
            throw new \RuntimeException('演示资源目录无效');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $source) {
            $sourcePath = $source->getPathname();
            if (is_link($sourcePath)) {
                continue;
            }
            $realSource = realpath($sourcePath);
            if ($realSource === false || !str_starts_with($realSource, $root . DIRECTORY_SEPARATOR)) {
                throw new \RuntimeException('演示资源路径越界');
            }

            $target = $destination . DIRECTORY_SEPARATOR . substr($realSource, strlen($root) + 1);
            if ($source->isDir()) {
                if (!is_dir($target) && !@mkdir($target, 0o755, true) && !is_dir($target)) {
                    throw new \RuntimeException('无法创建演示资源目录');
                }
                continue;
            }
            if (is_file($target)) {
                continue;
            }
            if (!is_dir(dirname($target)) && !@mkdir(dirname($target), 0o755, true) && !is_dir(dirname($target))) {
                throw new \RuntimeException('无法创建演示资源目录');
            }
            if (!@copy($realSource, $target)) {
                throw new \RuntimeException('无法复制演示资源');
            }
        }
    }

    /** @param array<string, mixed> $input */
    public function testDatabase(array $input): void
    {
        try {
            DatabaseInstaller::connect([
                'host'     => (string) $input['db_host'],
                'port'     => $input['db_port'],
                'username' => (string) $input['db_user'],
                'password' => (string) $input['db_password'],
            ])->query('SELECT 1');
        } catch (\Throwable $e) {
            throw new BusinessException('数据库无法连接', 400, $e);
        }
    }

    /** @param array<string, mixed> $input */
    public function testRedis(array $input): void
    {
        $redis = new \Redis();
        try {
            if ($redis->connect((string) $input['redis_host'], (int) $input['redis_port'], 2.0) !== true) {
                throw new BusinessException('Redis 无法连接');
            }
            $password = (string) ($input['redis_password'] ?? '');
            if ($password !== '') {
                $redis->auth($password);
            }
            $redis->select((int) $input['redis_db']);
            $redis->ping();
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new BusinessException('Redis 无法连接', 400, $e);
        } finally {
            try {
                $redis->close();
            } catch (\Throwable) {
            }
        }
    }

    /** @param array<string, mixed> $overrides */
    private function injectMysqlConfig(array $overrides): void
    {
        $merged = array_replace((array) config('database.connections.mysql'), $overrides);

        $property = new \ReflectionProperty(Config::class, 'config');
        /** @var array<string, mixed> $all */
        $all = $property->getValue();
        $all['database']['connections']['mysql'] = $merged;
        $property->setValue(null, $all);
        (new \ReflectionProperty(Config::class, 'flatCache'))->setValue(null, []);

        $capsule = (new \ReflectionProperty(Capsule::class, 'instance'))->getValue();
        if ($capsule instanceof Capsule) {
            $capsule->addConnection($merged, 'mysql');
            $capsule->getDatabaseManager()->purge('mysql');
        }
    }

    /** @param array<string, mixed> $overrides */
    private function injectRedisConfig(array $overrides): void
    {
        $merged = array_replace((array) config('redis.default'), $overrides);

        $property = new \ReflectionProperty(Config::class, 'config');
        /** @var array<string, mixed> $all */
        $all = $property->getValue();
        $all['redis']['default'] = $merged;
        $property->setValue(null, $all);
        (new \ReflectionProperty(Config::class, 'flatCache'))->setValue(null, []);

        (new \ReflectionProperty(Redis::class, 'instance'))->setValue(null, null);
        (new \ReflectionProperty(Redis::class, 'config'))->setValue(null, []);
        (new \ReflectionProperty(RedisManager::class, 'pools'))->setValue(null, []);
    }
}
