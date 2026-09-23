<?php

declare(strict_types=1);

namespace tests\Feature\DataImport;

use support\Db;
use tests\Support\ApiTestCase;

final class UserImportTest extends ApiTestCase
{
    /** @var list<int> */
    private array $userIds = [];

    protected function tearDown(): void
    {
        if ($this->userIds !== []) {
            Db::table('users')->whereIn('id', $this->userIds)->delete();
        }
        foreach (glob(runtime_path() . '/imports/*.csv') ?: [] as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public function test_user_import_creates_rows_and_skips_duplicates(): void
    {
        $admin = $this->actingAsAdmin(['user.import']);
        $ok = '138' . str_pad((string) random_int(0, 9999_9999), 8, '0', STR_PAD_LEFT);
        $dup = '139' . str_pad((string) random_int(0, 9999_9999), 8, '0', STR_PAD_LEFT);
        $existing = (int) Db::table('users')->insertGetId([
            'mobile'     => $dup,
            'nickname'   => '已有',
            'status'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->userIds[] = $existing;

        $csv = "mobile,nickname,password,email,gender,status\n"
            . "{$ok},导入用户,Secret1,a@example.com,1,1\n"
            . "{$dup},重复,,b@example.com,0,1\n"
            . "bad,非法,,,,,,\n";

        $data = $this->postFile(
            '/adminapi/user/import',
            'file',
            'users.csv',
            $csv,
            'text/csv',
            $admin->token
        )->assertOk()->data();
        $this->track('data_imports', (int) $data['id']);

        $this->assertSame(3, (int) $data['total_count']);
        $this->assertSame(1, (int) $data['success_count']);
        $this->assertSame(2, (int) $data['fail_count']);
        $created = Db::table('users')->where('mobile', $ok)->first();
        $this->assertNotNull($created);
        $this->userIds[] = (int) $created->id;
        $this->assertSame('导入用户', $created->nickname);
        $this->assertSame('a@example.com', $created->email);
        $this->assertNotSame('', (string) $created->password);
        $this->assertTrue(password_verify('Secret1', (string) $created->password));
        $this->assertSame(1, (int) Db::table('users')->where('mobile', $dup)->count());
    }

    /** 行失败只回业务文案：底层 SQL 异常会把绑定值（密码哈希）与库主机拼进消息，不能落库给管理员看。 */
    public function test_row_failure_message_never_carries_raw_sql_or_password_hash(): void
    {
        $admin = $this->actingAsAdmin(['user.import']);
        $mobile = '137' . str_pad((string) random_int(0, 9999_9999), 8, '0', STR_PAD_LEFT);
        $csv = "mobile,nickname,password,email,gender,status\n"
            . "{$mobile}," . str_repeat('长', 80) . ",Secret1,c@example.com,1,1\n";

        $data = $this->postFile('/adminapi/user/import', 'file', 'users.csv', $csv, 'text/csv', $admin->token)
            ->assertOk()->data();
        $this->track('data_imports', (int) $data['id']);

        $this->assertSame(1, (int) $data['fail_count']);
        $message = (string) $data['errors'][0]['message'];
        $this->assertSame(lang('dataimport.row_failed'), $message);
        $stored = (string) Db::table('data_imports')->where('id', $data['id'])->value('errors');
        foreach (['$2y$', 'insert into', 'Database:', 'Host:', $mobile] as $secret) {
            $this->assertStringNotContainsString($secret, $stored);
        }
    }

    /** 上传的临时 CSV 带着手机号与明文密码，导入结束必须删掉。 */
    public function test_uploaded_temp_file_is_removed_after_import(): void
    {
        $admin = $this->actingAsAdmin(['user.import']);
        $mobile = '136' . str_pad((string) random_int(0, 9999_9999), 8, '0', STR_PAD_LEFT);
        $before = glob(runtime_path() . '/imports/*.csv') ?: [];

        $data = $this->postFile(
            '/adminapi/user/import',
            'file',
            'users.csv',
            "mobile,nickname,password,email,gender,status\n{$mobile},留痕,Secret1,d@example.com,1,1\n",
            'text/csv',
            $admin->token
        )->assertOk()->data();
        $this->track('data_imports', (int) $data['id']);
        $created = Db::table('users')->where('mobile', $mobile)->first();
        if ($created !== null) {
            $this->userIds[] = (int) $created->id;
        }

        $this->assertSame($before, glob(runtime_path() . '/imports/*.csv') ?: [], '导入完成后不留临时文件');
    }

    /** 通用导入口不能当会员导入用：那条路只认 user.import。 */
    public function test_generic_upload_rejects_user_module(): void
    {
        // 通用导入权限不进角色树，超管是唯一拿得到它的人；这里钉的是「模块被拒」，与权限无关。
        $admin = $this->actingAsAdmin('super');
        $mobile = '135' . str_pad((string) random_int(0, 9999_9999), 8, '0', STR_PAD_LEFT);

        $this->postFile(
            '/adminapi/dataimport/upload',
            'file',
            'users.csv',
            "mobile,nickname\n{$mobile},绕过\n",
            'text/csv',
            $admin->token,
            ['module' => 'user']
        )->assertCode(400);

        $this->assertSame(0, (int) Db::table('users')->where('mobile', $mobile)->count());
    }

    public function test_user_import_requires_permission(): void
    {
        $admin = $this->actingAsAdmin(['user.list']);
        $this->postFile(
            '/adminapi/user/import',
            'file',
            'users.csv',
            "mobile\n13800001111\n",
            'text/csv',
            $admin->token
        )->assertCode(403);
    }

    public function test_import_template_is_csv(): void
    {
        $admin = $this->actingAsAdmin(['user.import']);
        $response = $this->get('/adminapi/user/import-template', [], $admin->token);
        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('text/csv', (string) $response->header('Content-Type'));
        $this->assertStringContainsString('mobile,nickname,password,email,gender,status', $response->body());
    }
}
