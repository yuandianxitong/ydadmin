<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// 1. 加载 .env 后强制切到测试库与测试 Redis DB（必须在任何 config 读取之前）
$dotenv = Dotenv\Dotenv::createMutable(dirname(__DIR__));
$dotenv->safeLoad();
// 开发库名（.env 的原值）：下面几行会把 DB_NAME 改成测试库，先留一份给第 4.5 步的开发库过期检查
$devDatabase = (string) ($_ENV['DB_NAME'] ?? 'dev007_ydadmin');
$overrides = [
    'DB_NAME'   => ($_ENV['DB_NAME'] ?? 'dev007_ydadmin') . '_test',
    'DB_PREFIX' => '',
    'REDIS_DB'  => '15',
    'CORS_ALLOWED_ORIGINS' => 'http://allowed.test',
    // .env.example 默认 debug=false，本机 .env 恰好是 true；API 文档两条路由只在 debug 为真时
    // 注册，且路由经 TestCase::ensureRoutesLoaded() 只加载一次，运行期 overrideConfig() 改不动
    // 已注册的路由表——不固定这个值，干净检出/CI 上 Test6 与 ApiDoc 系列测试就会红。
    'APP_DEBUG' => 'true',
    // 测试不起 queue 进程：投递即在当前进程调用消费者的 handle()，失败直接按「最后一次」进 failed_jobs（M3 设计决定 3、5）
    'QUEUE_DRIVER' => 'sync',
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

// 4.5 测试库结构自检：上一步已按指纹重建，这里是兜底——指纹算法本身有 bug 或安装脚本本身漏表时，
//     测试实际连接的就是这个库，在这里报错比让某条业务测试撞一个看不懂的 Table doesn't exist 更清楚。
$testStale = core\database\DevDatabaseGuard::missing($pdo, $testDb);
if ($testStale !== []) {
    fwrite(STDERR, "测试库 {$testDb} 结构不完整（" . implode('；', $testStale) . "），安装脚本或指纹逻辑有问题，无法继续测试\n");
    exit(1);
}

// 4.6 开发库过期提醒：只提醒、不阻断。测试连的是上面的 $testDb，从不触碰这个库，卡住整条测试流水线
//     换不来任何保护，而唯一能解除阻断的手段是对开发者本地库做破坏性 db:reset——谁都没有权限替开发者
//     做这个决定，所以这里只打一行清楚的警告，把决定权交还给开发者。
$devStale = core\database\DevDatabaseGuard::missing($pdo, $devDatabase);
if ($devStale !== []) {
    fwrite(STDERR, "警告：开发库 {$devDatabase} 结构已过期（" . implode('；', $devStale) . "），下次要跑本地业务前请执行 php webman db:reset\n");
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
