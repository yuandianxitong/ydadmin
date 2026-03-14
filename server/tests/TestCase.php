<?php
declare(strict_types=1);

namespace tests;

use think\App;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected App $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new App(dirname(__DIR__));
        $this->app->initialize();
    }

    protected function getService(string $class)
    {
        return $this->app->make($class);
    }

    protected function assertApiSuccess(array $response): void
    {
        $this->assertEquals(200, $response['code'], 'API response should be success: ' . ($response['message'] ?? ''));
    }

    protected function assertApiError(array $response, int $expectedCode = 0): void
    {
        $this->assertNotEquals(200, $response['code']);
        if ($expectedCode > 0) {
            $this->assertEquals($expectedCode, $response['code']);
        }
    }
}
