<?php

declare(strict_types=1);

namespace tests\RedLine;

use support\Db;
use tests\Support\ApiTestCase;

/**
 * 红线 Test15：危险扩展名一律拒绝，即使 storage_upload_allowed_ext 明确放行（spec §1.1 第 8 条、§7.2）。
 *
 * 为什么这是红线：/storage 是同源、无鉴权的静态直出目录（config/static.php + app\middleware\StaticFile）。
 * 一个 .svg / .html 落进去就是 stored-XSS 的载体，能拿到任意管理员的会话；配置项是后台表单填的，
 * 「填错了就等于关掉防护」不可接受，所以黑名单必须压过配置。
 *
 * 取样用 svg：它本来就在 storage_upload_allowed_ext 的种子值里（测试先断言这一点），
 * 所以「配置放行」不是人为构造出来的前提，而是出厂默认状态。
 */
final class Test15_DangerousExtensionRejectedTest extends ApiTestCase
{
    private const IMAGE = '/adminapi/upload/image';

    private const FILE = '/adminapi/upload/file';

    /**
     * public/storage 下所有文件的相对路径快照，用来证明「什么都没落盘」。
     *
     * @return list<string>
     */
    private function storageSnapshot(): array
    {
        $root = public_path('storage');
        if (!is_dir($root)) {
            return [];
        }
        $paths = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $file) {
            $paths[] = (string) $file->getPathname();
        }
        sort($paths);

        return $paths;
    }

    public function test_the_seeded_config_really_allows_svg(): void
    {
        $allowed = (string) Db::table('system_configs')->where('config_key', 'storage_upload_allowed_ext')->value('config_value');
        $this->assertContains('svg', array_map('trim', explode(',', $allowed)), 'Test15 的前提：种子配置放行 svg');
    }

    public function test_dangerous_extensions_are_rejected_on_both_endpoints(): void
    {
        $admin = $this->actingAsAdmin('super'); // 超管也不例外
        $filesBefore = Db::table('files')->count();
        $storageBefore = $this->storageSnapshot();
        $notAllowed = lang('business.file_type_not_allowed');

        // 图片端点：MIME 谎报成 image/png 通过白名单，落盘扩展名仍是 svg —— 必须拦住。
        // 这正是「MIME 校验通过但落盘的是可渲染文件」的绕过路径。
        $this->assertSame(
            $notAllowed,
            $this->postFile(self::IMAGE, 'file', 'payload.svg', '<svg onload=alert(1)>', 'image/png', $admin->token)->assertCode(400)->message()
        );
        $this->assertSame(
            $notAllowed,
            $this->postFile(self::IMAGE, 'file', 'payload.HTML', '<script>alert(1)</script>', 'image/png', $admin->token)->assertCode(400)->message(),
            '大小写不敏感'
        );

        // 文件端点：不限 MIME，但扩展名黑名单照样生效
        foreach (['payload.svg', 'shell.php', 'page.html', 'x.phtml', 'x.js'] as $filename) {
            $this->assertSame(
                $notAllowed,
                $this->postFile(self::FILE, 'file', $filename, 'PAYLOAD-RL15', 'application/octet-stream', $admin->token)->assertCode(400)->message(),
                "{$filename} 必须被拒绝"
            );
        }

        $this->assertSame($filesBefore, Db::table('files')->count(), '被拒绝的上传不得写 files 行');
        $this->assertSame($storageBefore, $this->storageSnapshot(), '被拒绝的上传不得在 public/storage 留下任何文件');
    }

    public function test_the_denylist_wins_even_when_the_config_lists_the_extension_explicitly(): void
    {
        $admin = $this->actingAsAdmin('super');
        // 把配置改成「只允许 svg」——配置层面它是唯一合法扩展名，黑名单仍然拒绝
        $this->setConfig('storage_upload_allowed_ext', 'svg');
        $filesBefore = Db::table('files')->count();
        $storageBefore = $this->storageSnapshot();

        foreach ([self::IMAGE, self::FILE] as $uri) {
            $this->assertSame(
                lang('business.file_type_not_allowed'),
                $this->postFile($uri, 'file', 'payload.svg', '<svg onload=alert(1)>', 'image/png', $admin->token)->assertCode(400)->message()
            );
        }

        $this->assertSame($filesBefore, Db::table('files')->count());
        $this->assertSame($storageBefore, $this->storageSnapshot());
    }
}
