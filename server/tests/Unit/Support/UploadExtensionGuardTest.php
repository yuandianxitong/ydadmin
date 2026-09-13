<?php

declare(strict_types=1);

namespace tests\Unit\Support;

use core\helper\UploadExtensionGuard;
use tests\TestCase;

/**
 * 危险扩展名黑名单与图片扩展名白名单的纯函数判定。
 * 黑名单的语义是「压过配置」：这里只证判定本身，端到端由红线 Test15 覆盖。
 */
final class UploadExtensionGuardTest extends TestCase
{
    public function test_renderable_and_executable_extensions_are_dangerous(): void
    {
        foreach (['html', 'htm', 'shtml', 'xhtml', 'xml', 'svg', 'svgz', 'js', 'mjs',
                  'php', 'php5', 'phtml', 'phar', 'cgi', 'pl', 'py', 'sh', 'bash',
                  'htaccess', 'asp', 'aspx', 'jsp', 'swf'] as $extension) {
            $this->assertTrue(UploadExtensionGuard::isDangerousExtension($extension), "{$extension} 应当命中黑名单");
        }
    }

    public function test_ordinary_document_extensions_are_not_dangerous(): void
    {
        foreach (['pdf', 'zip', 'rar', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'png', 'jpg'] as $extension) {
            $this->assertFalse(UploadExtensionGuard::isDangerousExtension($extension), "{$extension} 不应命中黑名单");
        }
    }

    public function test_matching_is_case_insensitive(): void
    {
        foreach (['SVG', 'Php', 'HTML', 'JS'] as $extension) {
            $this->assertTrue(UploadExtensionGuard::isDangerousExtension($extension));
        }
        $this->assertTrue(UploadExtensionGuard::isAllowedImageExtension('PNG'));
        $this->assertTrue(UploadExtensionGuard::isAllowedImageExtension('JpEg'));
    }

    public function test_image_whitelist(): void
    {
        foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $extension) {
            $this->assertTrue(UploadExtensionGuard::isAllowedImageExtension($extension));
        }
        // bmp 在 storage_upload_allowed_ext 种子里，但不在图片扩展名白名单里；svg 两头都不放行
        foreach (['bmp', 'svg', 'html', 'pdf', ''] as $extension) {
            $this->assertFalse(UploadExtensionGuard::isAllowedImageExtension($extension));
        }
    }

    public function test_empty_extension_is_not_image_and_not_dangerous(): void
    {
        // 空扩展名不靠这两个判定拦，由 UploadService 单独拒绝（见其 assertExtensionAllowed()）
        $this->assertFalse(UploadExtensionGuard::isDangerousExtension(''));
        $this->assertFalse(UploadExtensionGuard::isAllowedImageExtension(''));
    }
}
