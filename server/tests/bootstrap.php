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

// 4. 测试库不存在（或 YDADMIN_TEST_DB_RESET=1）时重建并导入 schema + init
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $_ENV['DB_HOST'] ?? '127.0.0.1', $_ENV['DB_PORT'] ?? '3306'),
    $_ENV['DB_USER'] ?? 'root',
    $_ENV['DB_PASSWORD'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$exists = $pdo->query('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ' . $pdo->quote($testDb))->fetch();
if (!$exists || getenv('YDADMIN_TEST_DB_RESET') === '1') {
    $pdo->exec("DROP DATABASE IF EXISTS `{$testDb}`");
    $pdo->exec("CREATE DATABASE `{$testDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    $pdo->exec("USE `{$testDb}`");
    foreach (['schema.sql', 'init.sql'] as $file) {
        $sql = (string) file_get_contents(dirname(__DIR__) . '/database/install/' . $file);
        foreach (splitSqlStatements($sql) as $statement) {
            $pdo->exec($statement);
        }
    }
}

/**
 * 按分号切分 SQL，识别单/双/反引号字符串与转义，避免字符串里的分号被误切；
 * 去掉 `--` 注释后为空的片段直接丢弃（MySQL 对纯注释语句报 "Query was empty"）。
 *
 * @return list<string>
 */
function splitSqlStatements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $quote = null;
    $length = strlen($sql);
    $flush = static function (string $chunk) use (&$statements): void {
        $trimmed = trim($chunk, "; \t\r\n");
        $withoutComments = trim((string) preg_replace('/^\s*--.*$/m', '', $trimmed));
        if ($withoutComments !== '') {
            $statements[] = $trimmed;
        }
    };
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $buffer .= $char;
        if ($quote !== null) {
            if ($char === '\\' && $quote !== '`' && $i + 1 < $length) {
                $buffer .= $sql[++$i];
                continue;
            }
            if ($char === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            continue;
        }
        if ($char === ';') {
            $flush($buffer);
            $buffer = '';
        }
    }
    $flush($buffer);
    return $statements;
}

// 5. 执行 config/bootstrap.php 注册的引导类（Eloquent 等）
foreach ((array) config('bootstrap', []) as $class) {
    if (method_exists($class, 'start')) {
        $class::start(null);
    }
}
