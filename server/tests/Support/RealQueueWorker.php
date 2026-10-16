<?php

declare(strict_types=1);

// 专用子进程引导：不运行 tests/bootstrap.php，避免重置父测试的数据库或 Redis。
require dirname(__DIR__, 2) . '/vendor/autoload.php';
Dotenv\Dotenv::createMutable(dirname(__DIR__, 2))->safeLoad();
$database = (string) getenv('YD_QUEUE_TEST_DATABASE');
$storage = (string) getenv('YD_QUEUE_TEST_STORAGE');
$work = (string) getenv('YD_QUEUE_TEST_RUNTIME');
if (!str_ends_with($database, '_test') || $storage === '' || $work === '') {
    throw new RuntimeException('Real queue worker requires isolated test resources');
}
foreach (['DB_NAME' => $database, 'REDIS_DB' => '15', 'QUEUE_DRIVER' => 'redis', 'TASK_STORAGE_PATH' => $storage] as $key => $value) {
    $_ENV[$key] = $_SERVER[$key] = $value;
    putenv($key . '=' . $value);
}
Webman\Config::clear();
support\App::loadAllConfig(['route']);
foreach ((array) config('bootstrap') as $bootstrap) {
    $bootstrap::start(null);
}
if (getenv('YD_TEST_IDEMPOTENCY_KEY')) {
    $userId = (int) getenv('YD_TEST_USER_ID');
    $response = support\Container::get(app\service\operations\IdempotencyService::class)->execute(
        (int) getenv('YD_TEST_ACTOR_ID'),
        'parallel-test',
        (string) getenv('YD_TEST_IDEMPOTENCY_KEY'),
        core\support\PayloadFingerprint::hash(['user_id' => $userId]),
        static function () use ($userId): Webman\Http\Response {
            support\Db::table('users')->where('id', $userId)->increment('points');
            usleep(100000);
            return core\response\Api::success(['points' => (int) support\Db::table('users')->where('id', $userId)->value('points')]);
        }
    );
    file_put_contents($work . '/' . getmypid() . '.json', $response->rawBody());
    exit(0);
}
Workerman\Worker::$pidFile = $work . '/worker.pid';
Workerman\Worker::$logFile = $work . '/worker.log';
$worker = new Workerman\Worker();
$worker->name = 'phase-two-test';
$worker->count = 2;
$worker->onWorkerStart = static function (): void {
    (new app\process\QueueConsumer(app_path('queue/redis_tasks'), 'tasks'))->onWorkerStart();
};
$argv = [__FILE__, 'start'];
$_SERVER['argv'] = $argv;
Workerman\Worker::runAll();
