<?php

declare(strict_types=1);

namespace tests\Unit\Http;

use tests\TestCase;

final class CorsConfigTest extends TestCase
{
    private ?string $originalCorsAllowedOrigins = null;

    protected function tearDown(): void
    {
        // Restore original env var in all three places
        $this->setOrUnsetEnv('CORS_ALLOWED_ORIGINS', $this->originalCorsAllowedOrigins);
        parent::tearDown();
    }

    public function test_empty_string_yields_empty_origins(): void
    {
        $this->originalCorsAllowedOrigins = $_ENV['CORS_ALLOWED_ORIGINS'] ?? null;
        $this->setOrUnsetEnv('CORS_ALLOWED_ORIGINS', '');

        $config = require base_path() . '/config/cors.php';

        $this->assertSame([], $config['allowed_origins']);
    }

    public function test_whitespace_only_yields_empty_origins(): void
    {
        $this->originalCorsAllowedOrigins = $_ENV['CORS_ALLOWED_ORIGINS'] ?? null;
        $this->setOrUnsetEnv('CORS_ALLOWED_ORIGINS', '   ');

        $config = require base_path() . '/config/cors.php';

        $this->assertSame([], $config['allowed_origins']);
    }

    public function test_unset_variable_yields_empty_origins(): void
    {
        $this->originalCorsAllowedOrigins = $_ENV['CORS_ALLOWED_ORIGINS'] ?? null;
        $this->setOrUnsetEnv('CORS_ALLOWED_ORIGINS', null);

        $config = require base_path() . '/config/cors.php';

        $this->assertSame([], $config['allowed_origins']);
    }

    public function test_multiple_origins_with_whitespace_yields_trimmed_origins(): void
    {
        $this->originalCorsAllowedOrigins = $_ENV['CORS_ALLOWED_ORIGINS'] ?? null;
        $this->setOrUnsetEnv('CORS_ALLOWED_ORIGINS', ' https://a.test , ,https://b.test ');

        $config = require base_path() . '/config/cors.php';

        $this->assertSame(['https://a.test', 'https://b.test'], $config['allowed_origins']);
    }

    private function setOrUnsetEnv(string $key, ?string $value): void
    {
        if ($value === null) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        } else {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}
