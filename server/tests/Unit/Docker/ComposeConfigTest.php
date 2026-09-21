<?php

declare(strict_types=1);

namespace tests\Unit\Docker;

use tests\TestCase;

final class ComposeConfigTest extends TestCase
{
    public function test_compose_config_mentions_four_services_and_not_dev_db(): void
    {
        $compose = dirname(__DIR__, 4) . '/docker/docker-compose.yml';
        $envExample = dirname(__DIR__, 4) . '/docker/.env.example';
        $this->assertFileExists($compose);
        $this->assertFileExists($envExample);

        $raw = (string) file_get_contents($compose);
        $this->assertStringNotContainsString('dev007_ydadmin', $raw);
        $this->assertStringNotContainsString('DROP DATABASE', $raw);
        $this->assertStringNotContainsString('initdb.d', $raw);
        $this->assertStringNotContainsString('8787', $raw);
        $this->assertStringContainsString('8000', $raw);
        $this->assertStringContainsString('8001', $raw);

        $nginx = (string) file_get_contents(dirname(__DIR__, 4) . '/docker/nginx/default.conf');
        $this->assertStringContainsString('proxy_pass http://webman:8000', $nginx);
        $this->assertStringContainsString('proxy_pass http://webman:8001', $nginx);
        $this->assertStringContainsString('location /ws', $nginx);

        if (trim((string) shell_exec('command -v docker')) === '') {
            $this->addToAssertionCount(1);

            return;
        }

        $cmd = 'docker compose -f ' . escapeshellarg($compose)
            . ' --env-file ' . escapeshellarg($envExample)
            . ' config';
        exec($cmd . ' 2>&1', $out, $code);
        $rendered = implode("\n", $out);
        $this->assertSame(0, $code, $rendered);
        foreach (['nginx', 'webman', 'mysql', 'redis'] as $name) {
            $this->assertStringContainsString($name, $rendered);
        }
        $this->assertStringNotContainsString('dev007_ydadmin', $rendered);
    }
}
