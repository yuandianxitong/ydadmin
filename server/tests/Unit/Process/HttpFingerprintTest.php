<?php

declare(strict_types=1);

namespace tests\Unit\Process;

use app\process\Http;
use core\plugin\WorkerFingerprintRegistry;
use tests\TestCase;

final class HttpFingerprintTest extends TestCase
{
    private int $otherPid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->otherPid = getmypid() + 100000;
        WorkerFingerprintRegistry::forget(getmypid());
        WorkerFingerprintRegistry::forget($this->otherPid);
    }

    protected function tearDown(): void
    {
        WorkerFingerprintRegistry::forget(getmypid());
        WorkerFingerprintRegistry::forget($this->otherPid);
        parent::tearDown();
    }

    public function test_on_worker_stop_removes_only_the_current_process_fingerprint(): void
    {
        WorkerFingerprintRegistry::write(getmypid(), 'fp-self');
        WorkerFingerprintRegistry::write($this->otherPid, 'fp-other');

        $http = (new \ReflectionClass(Http::class))->newInstanceWithoutConstructor();
        $http->onWorkerStop();

        $dir = WorkerFingerprintRegistry::dir();
        $this->assertFileDoesNotExist($dir . '/' . getmypid() . '.json');
        $this->assertFileExists($dir . '/' . $this->otherPid . '.json');
    }
}
