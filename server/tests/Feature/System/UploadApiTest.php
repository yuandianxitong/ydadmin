<?php

declare(strict_types=1);

namespace tests\Feature\System;

use app\service\system\UploadService;
use ReflectionClassConstant;
use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\TestAdmin;

/**
 * 上传接口（契约 §2.9.2、spec §6.5）：真实 multipart 请求 → 真实落盘 → 真实 files 行。
 * 大小与扩展名一律读 storage_* 配置（spec §1.1 第 8 条），不再是 TP8 写死的 2MB/10MB。
 */
final class UploadApiTest extends ApiTestCase
{
    private const IMAGE = '/adminapi/upload/image';

    private const FILE = '/adminapi/upload/file';

    /** 一小段 PNG 头 + 填充，够当作真实二进制内容用 */
    private function pngBytes(int $padding = 64): string
    {
        return "\x89PNG\r\n\x1a\n" . str_repeat("\x00\xff", $padding);
    }

    /**
     * 上传成功后登记落盘文件与 files 行，返回响应 data。
     *
     * @return array<string, mixed>
     */
    private function upload(string $uri, TestAdmin $admin, string $filename, string $mimeType, ?string $content = null): array
    {
        $data = $this->postFile($uri, 'file', $filename, $content ?? $this->pngBytes(), $mimeType, $admin->token)->assertOk()->data();
        $this->trackStorageFile((string) $data['path']);
        $id = Db::table('files')->where('path', $data['path'])->value('id');
        if ($id !== null) {
            $this->track('files', (int) $id);
        }

        return $data;
    }

    public function test_image_upload_stores_the_file_and_records_the_row(): void
    {
        $admin = $this->actingAsAdmin();
        $content = $this->pngBytes();

        $data = $this->upload(self::IMAGE, $admin, '我的头像.png', 'image/png', $content);

        // 响应形状：{url, path, filename, size, storage}
        $this->assertSame(['url', 'path', 'filename', 'size', 'storage'], array_keys($data));
        $this->assertMatchesRegularExpression('#^uploads/images/\d{8}/[0-9a-f]{32}\.png$#', (string) $data['path']);
        $this->assertSame('/storage/' . $data['path'], $data['url'], '本地驱动返回相对 URL');
        $this->assertSame(strlen($content), $data['size']);
        $this->assertSame('local', $data['storage']);
        // image 组返回的是生成的文件名（按天目录 + 随机名），不是原始文件名
        $this->assertSame(substr((string) $data['path'], strlen('uploads/images/')), $data['filename']);

        // 真的落到盘上了，内容一致
        $absolute = public_path('storage/' . $data['path']);
        $this->assertFileExists($absolute);
        $this->assertSame($content, file_get_contents($absolute));

        // files 行
        $row = Db::table('files')->where('path', $data['path'])->first();
        $this->assertNotNull($row, 'files 表应有对应记录');
        $this->assertSame('我的头像.png', $row->name, 'name 存原始文件名');
        $this->assertSame($data['url'], $row->url);
        $this->assertSame('image/png', $row->mime_type);
        $this->assertSame('png', $row->extension);
        $this->assertSame(strlen($content), (int) $row->size);
        $this->assertSame('images', $row->group);
        $this->assertSame($admin->id, (int) $row->upload_by);
        $this->assertSame('local', $row->storage);
    }

    public function test_file_upload_returns_the_original_filename(): void
    {
        $admin = $this->actingAsAdmin();

        $data = $this->upload(self::FILE, $admin, '季度报表.pdf', 'application/pdf', '%PDF-1.7 fake');

        $this->assertMatchesRegularExpression('#^uploads/files/\d{8}/[0-9a-f]{32}\.pdf$#', (string) $data['path']);
        $this->assertSame('季度报表.pdf', $data['filename'], 'files 组返回原始文件名——契约 §2.9.2 刻意的不对称');
        $this->assertFileExists(public_path('storage/' . $data['path']));
        $this->assertSame('files', Db::table('files')->where('path', $data['path'])->value('group'));
    }

    public function test_image_size_limit_comes_from_storage_image_max_size(): void
    {
        $admin = $this->actingAsAdmin();
        $big = str_repeat('A', 1024 * 1024 + 512); // 略大于 1MB

        $this->setConfig('storage_image_max_size', '1');
        $response = $this->postFile(self::IMAGE, 'file', 'big.png', $big, 'image/png', $admin->token)->assertCode(400);
        $this->assertSame(lang('business.image_size_exceeded', ['size' => 1]), $response->message());
        $this->assertSame(0, Db::table('files')->where('name', 'big.png')->count(), '超限不入库');

        // 放宽配置后同一个文件就能传——证明限制确实来自配置而不是写死的常量
        $this->setConfig('storage_image_max_size', '5');
        $data = $this->upload(self::IMAGE, $admin, 'big.png', 'image/png', $big);
        $this->assertSame(strlen($big), $data['size']);
    }

    public function test_file_size_limit_comes_from_storage_upload_max_size(): void
    {
        $admin = $this->actingAsAdmin();
        $big = str_repeat('B', 1024 * 1024 + 512);

        $this->setConfig('storage_upload_max_size', '1');
        $this->assertSame(
            lang('business.file_size_exceeded', ['size' => 1]),
            $this->postFile(self::FILE, 'file', 'big.pdf', $big, 'application/pdf', $admin->token)->assertCode(400)->message()
        );
    }

    public function test_allowed_extensions_come_from_storage_upload_allowed_ext(): void
    {
        $admin = $this->actingAsAdmin();
        $this->setConfig('storage_upload_allowed_ext', 'png,pdf');

        $this->assertSame(
            lang('business.file_type_not_allowed'),
            $this->postFile(self::FILE, 'file', 'note.txt', 'hello', 'text/plain', $admin->token)->assertCode(400)->message(),
            'txt 不在配置白名单里'
        );
        $this->assertSame(0, Db::table('files')->where('name', 'note.txt')->count());

        $data = $this->upload(self::FILE, $admin, 'note.pdf', 'application/pdf', 'hello');
        $this->assertSame('note.pdf', $data['filename']);
    }

    public function test_image_endpoint_rejects_non_image_mime_and_missing_extension(): void
    {
        $admin = $this->actingAsAdmin();

        $this->assertSame(
            lang('business.upload_image_only'),
            $this->postFile(self::IMAGE, 'file', 'note.txt', 'hello', 'text/plain', $admin->token)->assertCode(400)->message()
        );
        $this->assertSame(
            lang('business.file_type_not_allowed'),
            $this->postFile(self::IMAGE, 'file', 'noextension', $this->pngBytes(), 'image/png', $admin->token)->assertCode(400)->message()
        );
        $this->assertSame(
            lang('business.file_type_not_allowed'),
            $this->postFile(self::FILE, 'file', 'noextension', 'hello', 'application/octet-stream', $admin->token)->assertCode(400)->message()
        );
    }

    public function test_missing_file_is_rejected(): void
    {
        $admin = $this->actingAsAdmin();

        foreach ([self::IMAGE, self::FILE] as $uri) {
            $this->assertSame(lang('business.please_select_upload'), $this->post($uri, [], $admin->token)->assertCode(400)->message());
        }
    }

    public function test_upload_needs_login_only(): void
    {
        // 没有任何权限点的管理员也能传（#[PermissionSkip]）
        $nobody = $this->actingAsAdmin();
        $data = $this->upload(self::IMAGE, $nobody, 'x.png', 'image/png');
        $this->assertNotSame('', $data['url']);

        $this->postFile(self::IMAGE, 'file', 'x.png', $this->pngBytes(), 'image/png')->assertCode(401);
        $this->postFile(self::FILE, 'file', 'x.pdf', 'x', 'application/pdf')->assertCode(401);
    }

    public function test_a_failed_files_row_does_not_fail_the_upload(): void
    {
        $admin = $this->actingAsAdmin();
        // name 列是 varchar(255)：300 字的原始文件名会让 INSERT 在 MySQL 严格模式下失败。
        // 契约 §2.9.2：写 files 行失败只记日志，上传照样成功。
        $longName = str_repeat('长', 300) . '.png';
        $content = $this->pngBytes();

        $data = $this->postFile(self::IMAGE, 'file', $longName, $content, 'image/png', $admin->token)->assertOk()->data();
        $this->trackStorageFile((string) $data['path']);

        $this->assertFileExists(public_path('storage/' . $data['path']), '文件已经落盘');
        $this->assertSame(strlen($content), $data['size']);
        $this->assertSame(0, Db::table('files')->where('path', $data['path'])->count(), '入库失败被吞掉，不影响上传响应');
    }

    /**
     * `UploadService::DEFAULT_ALLOWED_EXT` 是种子 `storage_upload_allowed_ext` 的一份副本，
     * 在配置行被清空时它就是实际生效的白名单——两边漂移会静默改变上传行为，而没有任何东西钉住它们。
     * 测试库由 init.sql 重建，所以这里读到的 config_value 就是种子值。
     */
    public function test_default_allowed_ext_constant_stays_in_sync_with_the_seeded_config(): void
    {
        $seeded = Db::table('system_configs')->where('config_key', 'storage_upload_allowed_ext')->value('config_value');

        $this->assertSame((new ReflectionClassConstant(UploadService::class, 'DEFAULT_ALLOWED_EXT'))->getValue(), $seeded);
    }
}
