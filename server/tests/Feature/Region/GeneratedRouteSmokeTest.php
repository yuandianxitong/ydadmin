<?php

declare(strict_types=1);

namespace tests\Feature\Region;

use app\repository\region\RegionRepository;
use app\repository\version\AppVersionRepository;
use core\base\Repository;
use ReflectionProperty;
use tests\Support\ApiTestCase;

final class GeneratedRouteSmokeTest extends ApiTestCase
{
    public function test_admin_list_paths_require_auth_and_return_page(): void
    {
        foreach (['/adminapi/region/list', '/adminapi/version/list'] as $path) {
            $this->get($path)->assertCode(401);
            $admin = $this->actingAsAdmin('super');
            $data = $this->get($path, ['page' => 1, 'limit' => 10], $admin->token)->assertOk()->data();
            $this->assertSame(['list', 'pagination'], array_keys($data));
        }
    }

    public function test_region_and_version_repositories_do_not_redeclare_data_scoped(): void
    {
        foreach ([RegionRepository::class, AppVersionRepository::class] as $class) {
            $p = new ReflectionProperty($class, 'dataScoped');
            $this->assertSame(Repository::class, $p->getDeclaringClass()->getName(), $class);
            $this->assertFalse($p->getDefaultValue());
        }
    }

    public function test_admin_can_create_region_without_timestamp_columns(): void
    {
        $admin = $this->actingAsAdmin('super');
        $code = 't' . bin2hex(random_bytes(4));
        $created = $this->post('/adminapi/region', [
            'name' => '冒烟夹具',
            'code' => $code,
        ], $admin->token)->assertOk()->data();
        $this->track('regions', (int) $created['id']);
        $this->assertSame($code, $created['code']);
    }
}
