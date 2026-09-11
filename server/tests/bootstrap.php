<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// 1. 加载 .env 后强制切到测试库与测试 Redis DB（必须在任何 config 读取之前）
$dotenv = Dotenv\Dotenv::createMutable(dirname(__DIR__));
$dotenv->safeLoad();
$overrides = [
    'DB_NAME'   => ($_ENV['DB_NAME'] ?? 'dev007_ydadmin') . '_test',
    'DB_PREFIX' => '',
    'REDIS_DB'  => '15',
];
foreach ($overrides as $name => $value) {
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
    putenv("{$name}={$value}");
}
$testDb = $overrides['DB_NAME'];

// 2. 非阻塞独占锁：两个 phpunit 进程共用同一测试库会互相写脏数据，拿不到锁立即失败
$lockDir = dirname(__DIR__) . '/runtime';
if (!is_dir($lockDir)) {
    @mkdir($lockDir, 0o755, true);
}
$lockPath = $lockDir . '/phpunit-testdb.lock';
$lockHandle = fopen($lockPath, 'c+');
if ($lockHandle === false) {
    fwrite(STDERR, "无法创建测试库锁文件 {$lockPath}\n");
    exit(1);
}
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    $holderPid = trim((string) fread($lockHandle, 64)) ?: '未知';
    fwrite(STDERR, "另一个 phpunit 进程（pid {$holderPid}）正占用测试库 {$testDb}，等它退出再跑\n");
    exit(1);
}
ftruncate($lockHandle, 0);
rewind($lockHandle);
fwrite($lockHandle, (string) getmypid());
fflush($lockHandle);
$GLOBALS['__phpunit_testdb_lock'] = $lockHandle;

// 3. 加载 webman 配置（不加载路由；需要路由的测试调用 TestCase::ensureRoutesLoaded()）
Webman\Config::clear();
support\App::loadAllConfig(['route']);

// 4. 测试库不存在、安装脚本有变化（指纹不符）或 YDADMIN_TEST_DB_RESET=1 时，删库重建并导入 schema + init
$installDir = dirname(__DIR__) . '/database/install';
$fingerprintFile = $lockDir . '/phpunit-testdb.fingerprint';
$fingerprint = $testDb . ':' . core\database\DatabaseInstaller::fingerprint($installDir);
$pdo = core\database\DatabaseInstaller::connect((array) config('database.connections.mysql'));
$exists = $pdo->query('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ' . $pdo->quote($testDb))->fetch();
$recorded = is_file($fingerprintFile) ? (string) file_get_contents($fingerprintFile) : '';
if (!$exists || getenv('YDADMIN_TEST_DB_RESET') === '1' || $recorded !== $fingerprint) {
    core\database\DatabaseInstaller::reinstall($pdo, $testDb, $installDir);
    file_put_contents($fingerprintFile, $fingerprint);
}

// 5. 执行 config/bootstrap.php 注册的引导类（Eloquent 等）
foreach ((array) config('bootstrap', []) as $class) {
    if (method_exists($class, 'start')) {
        $class::start(null);
    }
}

// 6. 清空测试 Redis（DB 15 专供测试）。测试库重建后自增 id 会复用，不清空会读到
//    上一轮留下的权限 / 数据范围 / token 版本缓存。
if ((int) config('redis.default.database') !== 15) {
    fwrite(STDERR, "测试 Redis 必须是 DB 15，当前为 " . config('redis.default.database') . "\n");
    exit(1);
}
support\Redis::connection('default')->flushdb();
