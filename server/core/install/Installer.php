<?php

declare(strict_types=1);

namespace core\install;

use core\contract\SuperAdminInitializer;
use core\database\DatabaseInstaller;
use core\database\SqlScript;
use core\exception\BusinessException;
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
    ) {
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
            if (preg_match('/^\s*' . $key . '\s*=\s*"?([^"\r\n]*)"?\s*$/m', $text, $m) !== 1 || trim($m[1]) === '') {
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
            $this->runLocked($pdo, $input, $database);
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('ydadmin:install')");
        }
    }

    /** @param array<string, mixed> $input */
    private function runLocked(\PDO $pdo, array $input, string $database): void
    {
        $exists = $pdo->query(
            'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ' . $pdo->quote($database)
        )->fetch();
        if ($exists === false) {
            $pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
        }
        $pdo->exec("USE `{$database}`");
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
        file_put_contents($this->lockPath, date('c'));
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
