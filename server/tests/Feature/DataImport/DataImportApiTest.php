<?php

declare(strict_types=1);

namespace tests\Feature\DataImport;

use support\Db;
use tests\Support\ApiTestCase;

final class DataImportApiTest extends ApiTestCase
{
    public function test_upload_counts_rows_and_writes_history_not_files(): void
    {
        $admin = $this->actingAsAdmin('super');
        $filesBefore = (int) Db::table('files')->count();
        $csv = "name,code\nfoo,1\nbar,2\n";
        $data = $this->postFile(
            '/adminapi/dataimport/upload',
            'file',
            'demo.csv',
            $csv,
            'text/csv',
            $admin->token,
            ['module' => 'demo', 'field_map' => '{"name":"name","code":"code"}']
        )->assertOk()->data();
        $this->track('data_imports', (int) $data['id']);
        $this->assertSame(2, (int) $data['total_count']);
        $this->assertSame(2, (int) $data['success_count']);
        $this->assertSame(0, (int) $data['fail_count']);
        $row = Db::table('data_imports')->where('id', $data['id'])->first();
        $this->assertSame($admin->id, (int) $row->admin_id);
        $this->assertSame(1, (int) $row->status);
        $this->assertSame($filesBefore, (int) Db::table('files')->count());

        $hist = $this->get('/adminapi/dataimport/history', ['module' => 'demo', 'page' => 1, 'limit' => 20], $admin->token)->assertOk()->data();
        $this->assertSame(['list', 'pagination'], array_keys($hist));
        $this->assertContains((int) $data['id'], array_map('intval', array_column($hist['list'], 'id')));
    }

    public function test_upload_rejects_missing_file_and_non_csv(): void
    {
        $admin = $this->actingAsAdmin('super');
        $this->post('/adminapi/dataimport/upload', ['module' => 'demo'], $admin->token)->assertCode(400);
        $this->postFile(
            '/adminapi/dataimport/upload',
            'file',
            'demo.txt',
            "a,b\n1,2\n",
            'text/plain',
            $admin->token,
            ['module' => 'demo']
        )->assertCode(400);
        $empty = $this->postFile(
            '/adminapi/dataimport/upload',
            'file',
            'empty.csv',
            "",
            'text/csv',
            $admin->token,
            ['module' => 'demo']
        )->assertOk()->data();
        $this->track('data_imports', (int) $empty['id']);
        $this->assertSame(2, (int) $empty['status']);
        $this->assertSame(0, (int) $empty['total_count']);
    }

    protected function tearDown(): void
    {
        foreach (glob(runtime_path() . '/imports/*.csv') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }
}
