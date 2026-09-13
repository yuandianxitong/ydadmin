<?php

declare(strict_types=1);

namespace core\helper;

/**
 * 上传扩展名判定（移植 Saas core\helper\UploadExtensionGuard，去掉本项目用不到的 CSV 白名单）。
 *
 * 背景：/storage 是同源、无鉴权的静态直出目录（config/static.php 打开了 public 静态资源，
 * app\middleware\StaticFile 只拦点号开头的隐藏文件并补 X-Content-Type-Options: nosniff）。
 * 一个 .html / .svg 落进去就能被浏览器当页面渲染，构成 stored-XSS 载体；若将来换成
 * nginx + php-fpm 直指 public/，.php 还会升级成 RCE。
 *
 * 两层判定：
 *   - isDangerousExtension()：黑名单。可渲染 HTML / 可执行脚本的扩展名一律拒绝，
 *     其余（pdf/zip/docx 等常规文档类型）放行。**它压过 storage_upload_allowed_ext 配置**——
 *     后台表单里把 svg 填进白名单也不放行（spec §1.1 第 8 条，红线 Test15 钉住这条）。
 *   - isAllowedImageExtension()：白名单，upload/image 用。即使 MIME 已过
 *     UploadService::ALLOWED_IMAGE_MIME 校验，落盘扩展名取自客户端提交的原始文件名
 *     （UploadFile::getUploadExtension()），必须独立白名单化：否则 image/png 的 MIME
 *     配上 x.html 的文件名就能绕过 MIME 检查，落盘出一个 .html。
 *
 * 纯函数 / 静态方法，无任何可变状态，符合 scripts/check-context-discipline.sh 的常驻内存纪律。
 */
final class UploadExtensionGuard
{
    /**
     * 可渲染 HTML / 可执行脚本的危险扩展名黑名单（小写）。
     *
     * @var list<string>
     */
    private const DANGEROUS_EXTENSIONS = [
        'html', 'htm', 'shtml', 'xhtml', 'xht', 'xml',
        'svg', 'svgz',
        'js', 'mjs',
        'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar', 'pht', 'pgif',
        'cgi', 'pl', 'py', 'sh', 'bash',
        'htaccess',
        'asp', 'aspx', 'jsp',
        'swf',
    ];

    /**
     * upload/image 允许的图片扩展名白名单（小写），与 UploadService::ALLOWED_IMAGE_MIME 的
     * jpeg/png/gif/webp 一一对应。
     *
     * @var list<string>
     */
    private const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** 判定扩展名是否命中危险扩展名黑名单（大小写不敏感）。 */
    public static function isDangerousExtension(string $extension): bool
    {
        return in_array(strtolower($extension), self::DANGEROUS_EXTENSIONS, true);
    }

    /** 判定扩展名是否在图片扩展名白名单内（大小写不敏感）。 */
    public static function isAllowedImageExtension(string $extension): bool
    {
        return in_array(strtolower($extension), self::ALLOWED_IMAGE_EXTENSIONS, true);
    }
}
