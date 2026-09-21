<?php

declare(strict_types=1);

namespace tests\Feature\Version;

use tests\Support\ApiTestCase;

final class VersionApiTest extends ApiTestCase
{
    public function test_check_returns_latest_enabled_and_ignores_disabled(): void
    {
        $admin = $this->actingAsAdmin('super');
        $s = bin2hex(random_bytes(2));
        $low = $this->post('/adminapi/version', [
            'platform' => 'android', 'version' => '1.0.' . $s, 'version_code' => 10,
            'download_url' => '', 'description' => 'low', 'force_update' => 0, 'status' => 1,
        ], $admin->token)->assertOk()->data();
        $high = $this->post('/adminapi/version', [
            'platform' => 'android', 'version' => '2.0.' . $s, 'version_code' => 20,
            'download_url' => 'https://ex.test/a.apk', 'description' => 'high', 'force_update' => 1, 'status' => 1,
        ], $admin->token)->assertOk()->data();
        $disabled = $this->post('/adminapi/version', [
            'platform' => 'android', 'version' => '9.0.' . $s, 'version_code' => 90,
            'download_url' => '', 'description' => 'off', 'force_update' => 1, 'status' => 0,
        ], $admin->token)->assertOk()->data();
        $this->track('app_versions', (int) $low['id']);
        $this->track('app_versions', (int) $high['id']);
        $this->track('app_versions', (int) $disabled['id']);

        $this->post('/adminapi/version', [
            'platform' => 'windows', 'version' => '1.0.0', 'version_code' => 1,
        ], $admin->token)->assertCode(422);

        $none = $this->get('/api/version/check', ['platform' => 'android', 'version_code' => 20])->assertOk()->data();
        $this->assertFalse($none['need_update']);
        $this->assertFalse($none['force_update']);

        $hit = $this->get('/api/version/check', ['platform' => 'android', 'version_code' => 10])->assertOk()->data();
        $this->assertTrue($hit['need_update']);
        $this->assertTrue($hit['force_update']);
        $this->assertSame(20, (int) $hit['version_code']);
        $this->assertSame('https://ex.test/a.apk', $hit['download_url']);

        $this->get('/api/version/check')->assertCode(400);
        $this->get('/api/version/check', ['platform' => 'android', 'version_code' => 0])->assertCode(400);
    }
}
