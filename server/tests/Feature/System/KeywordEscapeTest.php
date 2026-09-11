<?php

declare(strict_types=1);

namespace tests\Feature\System;

use support\Db;
use tests\Support\ApiTestCase;

final class KeywordEscapeTest extends ApiTestCase
{
    private function probe(): string
    {
        return 'kw' . bin2hex(random_bytes(3));
    }

    public function test_admin_keyword_treats_percent_literally(): void
    {
        $super = $this->actingAsAdmin('super');
        $p = $this->probe();
        $hit = $this->actingAsAdmin([], ['nickname' => "{$p}100%"]);
        $this->actingAsAdmin([], ['nickname' => "{$p}1000"]);

        $list = $this->get('/adminapi/system/admin', ['keyword' => "{$p}100%"], $super->token)->assertOk()->data()['list'];
        $this->assertSame([$hit->id], array_map('intval', array_column($list, 'id')));
    }

    public function test_role_keyword_treats_underscore_literally(): void
    {
        $super = $this->actingAsAdmin('super');
        $p = $this->probe();
        $now = date('Y-m-d H:i:s');
        $ids = [];
        foreach (["{$p}a_b", "{$p}axb"] as $i => $title) {
            $ids[$i] = (int) Db::table('roles')->insertGetId([
                'name' => "{$p}_{$i}", 'title' => $title, 'data_scope' => 1, 'is_system' => 0, 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->track('roles', $ids[$i]);
        }

        $list = $this->get('/adminapi/system/role', ['keyword' => "{$p}a_b"], $super->token)->assertOk()->data()['list'];
        $this->assertSame([$ids[0]], array_map('intval', array_column($list, 'id')));
    }

    public function test_department_keyword_treats_underscore_literally(): void
    {
        $super = $this->actingAsAdmin('super');
        $p = $this->probe();
        $hit = $this->createDepartment(['name' => "{$p}a_b"]);
        $this->createDepartment(['name' => "{$p}axb"]);

        $tree = $this->get('/adminapi/system/department', ['keyword' => "{$p}a_b"], $super->token)->assertOk()->data();
        $this->assertSame([$hit], array_map('intval', array_column($tree, 'id')));
    }

    public function test_dictionary_keyword_treats_underscore_literally(): void
    {
        $super = $this->actingAsAdmin('super');
        $p = $this->probe();
        $hit = (int) $this->post('/adminapi/system/dictionary', ['name' => "{$p}a_b", 'code' => "{$p}_hit"], $super->token)->assertOk()->data()['id'];
        $this->track('dictionaries', $hit);
        $miss = (int) $this->post('/adminapi/system/dictionary', ['name' => "{$p}axb", 'code' => "{$p}_miss"], $super->token)->assertOk()->data()['id'];
        $this->track('dictionaries', $miss);

        $list = $this->get('/adminapi/system/dictionary', ['keyword' => "{$p}a_b"], $super->token)->assertOk()->data()['list'];
        $this->assertSame([$hit], array_map('intval', array_column($list, 'id')));
    }
}
