<?php

declare(strict_types=1);

namespace tests\Feature\System;

use app\command\ApiExportCommand;
use app\resource\announcement\AnnouncementResource;
use Symfony\Component\Console\Tester\CommandTester;
use tests\Support\ApiTestCase;

final class PhaseOneContractTest extends ApiTestCase
{
    public function test_lists_reject_malformed_filters_and_keep_pagination_compatibility(): void
    {
        $admin = $this->actingAsAdmin('super');
        foreach (['/adminapi/announcement/list', '/adminapi/article/list', '/adminapi/system/dictionary'] as $path) {
            $this->get($path, ['status' => ['0']], $admin->token)->assertCode(422);
            $this->get($path, ['page' => 'bad'], $admin->token)->assertCode(422);
            $data = $this->get($path, ['status' => '', 'page_no' => 1, 'page_size' => 999], $admin->token)->assertOk()->data();
            $this->assertSame(100, $data['pagination']['per_page']);
        }
    }

    public function test_create_detail_and_list_share_response_fields_and_server_ownership(): void
    {
        $admin = $this->actingAsAdmin('super');
        $created = $this->post('/adminapi/announcement', ['title' => 'contract projection', 'type' => 1, 'status' => 0, 'created_by' => 999999, 'is_admin' => true], $admin->token)->assertOk()->data();
        $this->track('announcements', (int) $created['id']);
        $this->assertSame($admin->id, $created['created_by']);
        $this->assertArrayNotHasKey('is_admin', $created);
        $this->assertSame([], array_diff(array_keys($created), array_keys(AnnouncementResource::schema()['properties'])));
        $detail = $this->get('/adminapi/announcement/detail/' . $created['id'], [], $admin->token)->assertOk()->data();
        $this->assertSame($created['id'], $detail['id']);
        $rows = $this->get('/adminapi/announcement/list', ['keyword' => 'contract projection', 'status' => '0'], $admin->token)->assertOk()->data()['list'];
        $this->assertContains($created['id'], array_column($rows, 'id'));
    }

    public function test_export_writes_actual_document_and_matching_types(): void
    {
        self::ensureRoutesLoaded();
        $directory = sys_get_temp_dir() . '/yd-contract-' . bin2hex(random_bytes(4));
        $tester = new CommandTester(new ApiExportCommand());
        try {
            $this->assertSame(0, $tester->execute(['--output' => $directory]));
            $document = json_decode(file_get_contents($directory . '/openapi-admin.json'), true, 512, JSON_THROW_ON_ERROR);
            $this->assertArrayHasKey('/adminapi/announcement/list', $document['paths']);
            $types = file_get_contents($directory . '/admin.d.ts');
            $this->assertStringContainsString('"GET /adminapi/announcement/list"', $types);
            $this->assertStringContainsString('"title"?: string', $types);
        } finally {
            foreach (glob($directory . '/*') ?: [] as $path) {
                unlink($path);
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }
}
