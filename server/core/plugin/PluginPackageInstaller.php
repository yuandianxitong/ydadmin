<?php

declare(strict_types=1);

namespace core\plugin;

use core\exception\BusinessException;
use core\plugin\contracts\PluginHostProfile;
use ZipArchive;

/**
 * 插件 zip 安装器：inspect() 校验结构并解析 plugin.json、不落盘；extract() 校验后真正解压。
 *
 * - 解压体积按实际 inflate 出的字节计量，不信 statIndex()['size']：那是打包方自填的声明，可平凡改小。
 * - DANGEROUS_PATTERNS 只是纵深防御，字符串拼接、call_user_func 等手法都能绕过，真正的边界是谁能上传 zip。
 *   getFromIndex() 也按声明的大小截断，改小 size 字段就能让扫描器看到空内容。
 */
class PluginPackageInstaller
{
    private const MAX_SIZE = 50 * 1024 * 1024; // 50 MB（压缩后体积）

    /** 解压后总体积上限，防高压缩比 zip 炸弹撑爆磁盘。 */
    public const MAX_UNCOMPRESSED = 500 * 1024 * 1024; // 500 MB

    /** 条目数上限，防海量小文件放大后续逐条目扫描的开销。 */
    public const MAX_ENTRIES = 5000;

    /**
     * 流式读写块大小（256KB）。体积计量与解压都必须"边读边计数、读完即弃"，
     * 绝不能把整个条目读进内存——否则单个高压缩比条目就能在计量阶段把
     * worker 的内存吃光，等于把磁盘炸弹换成了内存炸弹。
     */
    private const STREAM_CHUNK = 256 * 1024;

    private const ALLOWED_EXTENSIONS = [
        'php', 'ts', 'tsx', 'vue', 'js', 'mjs', 'cjs',
        'json', 'yaml', 'yml',
        'css', 'scss', 'less',
        'png', 'jpg', 'jpeg', 'svg', 'ico', 'gif', 'webp',
        'md', 'txt', 'sql',
    ];

    // 前端源码统一在 src/（src/tenant、src/platform、src/pc、src/uniapp），产物在 dist/；
    // 旧布局的顶层 tenant|pc|uniapp 硬拒，旧包用 plugin:pack 重打。
    // PluginPacker::pack() 打包时直接引用本常量，不要在别处复制。
    public const ALLOWED_TOP_DIRS = [
        'app', 'src', 'dist', 'database', 'Config', 'config',
        'tests', // tests: 插件测试随包分发(闭源插件的测试与备份载体)
        'assets', // 旧包顶层；新包装修图标在 src/tenant/assets/diy/，随 src/ 打进 zip
    ];

    // 不放行顶层 composer.json：插件 PSR-4 由 PluginLoader 运行时注册，不走 composer；
    // 而 autoload.files 只有重启整个 worker 池才会生效，插件生效编排只做 reload。
    private const ALLOWED_TOP_FILES = [
        'plugin.json', 'README.md', 'LICENSE', 'CHANGELOG.md',
    ];

    private const DANGEROUS_PATTERNS = [
        'eval'            => '/\beval\s*\(/i',
        'assert'          => '/\bassert\s*\(/i',
        'system'          => '/\bsystem\s*\(/i',
        'exec'            => '/\bexec\s*\(/i',
        'shell_exec'      => '/\bshell_exec\s*\(/i',
        'passthru'        => '/\bpassthru\s*\(/i',
        'proc_open'       => '/\bproc_open\s*\(/i',
        'popen'           => '/\bpopen\s*\(/i',
        'pcntl_exec'      => '/\bpcntl_exec\s*\(/i',
        'create_function' => '/\bcreate_function\s*\(/i',
        // 注意：PHP 反引号操作符（shell 执行）已通过 shell_exec/system/passthru/exec/proc_open/popen/pcntl_exec
        // 几个函数黑名单覆盖；而 MySQL 反引号引用标识符（e.g. CREATE TABLE `foo`）是合法常见用法。
        // 单凭 regex 难以区分两者，故不扫描反引号，避免大量误伤。
        'dynamic_include' => '/\b(?:include|require)(?:_once)?\s+\$/',
    ];

    /** 同步进小程序主包的 DIY 渲染器目录；禁止从此处引用分包 */
    private const DIY_UNIAPP_PREFIX = 'src/uniapp/components/diy/';

    public function __construct(private readonly PluginHostProfile $profile)
    {
    }

    /**
     * 校验 zip + 解析 plugin.json，不解压到磁盘。
     *
     * @return array<string, mixed> 解析后的 manifest
     */
    public function inspect(string $zipPath): array
    {
        if (!is_file($zipPath)) {
            throw new BusinessException("zip 文件不存在：{$zipPath}", 422);
        }
        if (filesize($zipPath) > self::MAX_SIZE) {
            throw new BusinessException('zip 文件超过 50MB 限制', 422);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new BusinessException('zip 文件无法打开（可能损坏）', 422);
        }

        try {
            // 顺序有讲究：validateEntries() 只读元数据，最便宜；assertUncompressedBudget() 必须排在所有
            // 会解压内容的步骤之前，否则一个超大 plugin.json 会在预算检查前就被整个读进内存。
            $this->validateEntries($zip);
            $this->assertUncompressedBudget($zip);
            $manifest = $this->extractManifest($zip);
            $this->scanPhpEntries($zip);
            $this->validateSqlCleanup($zip);
            $this->validateDiyRenderers($zip, $manifest);
            $this->validatePcPages($zip, $manifest);
            // 包内有 src/tenant|pc/ 却没有对应 dist/manifest.json 时拒装；纯后端包放行。
            $code = (string) ($manifest['code'] ?? '');
            PluginAssetPublisher::assertTenantDistInZipIfNeeded($zip, $code);
            PluginAssetPublisher::assertPcDistInZipIfNeeded($zip, $code);
            return $manifest;
        } finally {
            $zip->close();
        }
    }

    /**
     * 解压到目标目录（调用前必须 inspect 过）。
     *
     * 本方法创建的目录由本方法在任何失败路径上自己删掉，残留的空目录会被下一次安装当成已解压好的代码。
     * 不用 extractTo()：它中途无法叫停，而解压体积必须在真实写盘时计量。
     */
    public function extract(string $zipPath, string $targetDir): void
    {
        if (is_dir($targetDir)) {
            throw new BusinessException("目标目录已存在：{$targetDir}", 409);
        }
        if (!mkdir($targetDir, 0o755, true) && !is_dir($targetDir)) {
            // mkdir 没成功，本方法没有创建任何东西，无需清理。
            throw new BusinessException("无法创建目录：{$targetDir}", 500);
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new BusinessException('zip 文件无法打开', 422);
            }
            try {
                $this->validateEntries($zip);
                $this->extractEntriesWithBudget($zip, $targetDir);
            } finally {
                $zip->close();
            }
        } catch (\Throwable $e) {
            $this->rrmdir($targetDir);
            throw $e;
        }
    }

    /**
     * 逐条目解压并维持运行中的字节预算，累计实际写出的字节数。
     *
     * 路径安全由调用方先跑的 validateEntries() 保证，这里不重复判断，所以不得在别处跳过它直接调用本方法。
     */
    private function extractEntriesWithBudget(ZipArchive $zip, string $targetDir): void
    {
        $base  = rtrim($targetDir, '/');
        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || $name === '') {
                continue;
            }
            $dest = $base . '/' . $name;

            if (str_ends_with($name, '/')) {
                $this->ensureDir(rtrim($dest, '/'));
                continue;
            }

            $this->ensureDir(dirname($dest));

            $in = $zip->getStreamIndex($i);
            if ($in === false) {
                throw new BusinessException("zip 条目无法读取：{$name}", 500);
            }
            $out = fopen($dest, 'wb');
            if ($out === false) {
                fclose($in);
                throw new BusinessException("无法写入文件：{$dest}", 500);
            }

            try {
                while (($chunk = fread($in, self::STREAM_CHUNK)) !== false && $chunk !== '') {
                    $total += strlen($chunk);
                    if ($total > self::MAX_UNCOMPRESSED) {
                        throw new BusinessException('zip 解压后总体积超过 500MB 限制', 422);
                    }
                    if (fwrite($out, $chunk) === false) {
                        throw new BusinessException("zip 解压写盘失败：{$dest}", 500);
                    }
                }
            } finally {
                fclose($in);
                fclose($out);
            }
        }
    }

    /**
     * 实测解压后总体积：逐条目分块读实际解压出的字节，读完即弃，累计超过 MAX_UNCOMPRESSED 立即拒绝。
     */
    private function assertUncompressedBudget(ZipArchive $zip): void
    {
        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || str_ends_with($name, '/')) {
                continue;
            }
            $stream = $zip->getStreamIndex($i);
            if ($stream === false) {
                continue;
            }
            try {
                while (($chunk = fread($stream, self::STREAM_CHUNK)) !== false && $chunk !== '') {
                    $total += strlen($chunk);
                    if ($total > self::MAX_UNCOMPRESSED) {
                        throw new BusinessException('zip 解压后总体积超过 500MB 限制', 422);
                    }
                }
            } finally {
                fclose($stream);
            }
        }
    }

    private function ensureDir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }
        if (!mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new BusinessException("无法创建目录：{$dir}", 500);
        }
    }

    /**
     * 递归删除 extract() 自己创建的解压目录。必须先排除软链：is_dir() 对指向目录的软链返回 true。
     */
    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $f) {
            $p = "$dir/$f";
            is_dir($p) && !is_link($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractManifest(ZipArchive $zip): array
    {
        $raw = $zip->getFromName('plugin.json');
        if ($raw === false) {
            throw new BusinessException('zip 内 plugin.json 不存在（必须在 zip 根目录）', 422);
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new BusinessException('plugin.json 不是合法 JSON', 422);
        }
        return $decoded;
    }

    /**
     * 路径是否不安全：绝对路径、`..` 或空字节。单独成方法供测试反射调用：
     * 当前 libzip 读写两端都会处理掉文件名里的空字节，`\0` 分支无法端到端构造。
     */
    private static function isUnsafePath(string $name): bool
    {
        return str_starts_with($name, '/') || str_contains($name, '..') || str_contains($name, "\0");
    }

    private function validateEntries(ZipArchive $zip): void
    {
        // 条目数上限，numFiles 是 O(1) 的真实值。
        if ($zip->numFiles > self::MAX_ENTRIES) {
            throw new BusinessException('zip 内条目数超过 ' . self::MAX_ENTRIES . ' 个限制', 422);
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }

            // 路径安全检查必须在跳过目录条目之前，否则名为 ../../evil/ 的目录条目永远不被检查。
            if (self::isUnsafePath($name)) {
                throw new BusinessException("zip 内路径不安全（绝对路径、.. 或空字节）：{$name}", 422);
            }

            // 跳过目录条目
            if (str_ends_with($name, '/')) {
                continue;
            }

            // migrations/、migration/ 旧布局硬拒绝，
            // 正则兼容 zip 顶层带/不带插件目录前缀两种打包方式。
            if (preg_match('#^(?:[^/]+/)?migrations?/#', $name)) {
                throw new BusinessException(
                    'migrations/ 布局已废弃（v2.28.0 起）：请改用 database/install.sql + database/updates/vX.Y.Z.sql，详见插件开发文档',
                    422
                );
            }

            // 扩展名白名单（先检查扩展名，确保危险扩展立即被拒绝）
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($ext !== '' && !in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                throw new BusinessException("zip 内扩展名不允许：.{$ext}（{$name}）", 422);
            }

            // 顶层文件/目录白名单
            $parts = explode('/', $name);
            $top   = $parts[0];
            if (count($parts) === 1) {
                $imageExts = ['png', 'jpg', 'jpeg', 'svg', 'ico', 'gif', 'webp'];
                $ext = strtolower(pathinfo($top, PATHINFO_EXTENSION));
                $isAllowedTopFile = in_array($top, self::ALLOWED_TOP_FILES, true)
                    || in_array($ext, $imageExts, true);
                if (!$isAllowedTopFile) {
                    throw new BusinessException("zip 顶层文件不在白名单：{$name}", 422);
                }
            } else {
                if (!in_array($top, self::ALLOWED_TOP_DIRS, true)) {
                    throw new BusinessException("zip 顶层目录不在白名单：{$top}/", 422);
                }
            }
        }
    }

    /**
     * 宽校验：声明了建表就必须声明清理（purge 依赖 uninstall.sql 删表）。
     * install.sql 不存在（zip 内没有 database/ 目录）时不校验。
     */
    private function validateSqlCleanup(ZipArchive $zip): void
    {
        $installSqlContent   = $zip->getFromName('database/install.sql');
        $uninstallSqlContent = $zip->getFromName('database/uninstall.sql');

        if ($installSqlContent !== false
            && preg_match('/\bCREATE\s+TABLE\b/i', $installSqlContent)
            && trim((string) $uninstallSqlContent) === '') {
            throw new BusinessException(
                'database/install.sql 含建表语句时必须提供非空的 database/uninstall.sql（清理数据用）',
                422
            );
        }
    }

    /**
     * DIY 渲染器协议 v1：manifest 声明了 renderer.<端> 的 widget，zip 内必须存在对应组件文件
     * （约定目录 <端>/components/diy/<裸组件名>.vue）。声明格式本身由 PluginManifestValidator 把关，
     * 本方法只做「声明了但文件没打进包」的存在性校验——plugin:pack 复用同一 inspect()，
     * 本地打包即拦截，这是闭源插件唯一备份手段（zip）下必须有的安全网。
     *
     * 另扫 uniapp/components/diy/*.{vue,ts} 的 import：组件会被同步进小程序主包，
     * 引用分包（@/modules/ 或相对路径逃出 diy 目录）会在微信端报 module is not defined。
     *
     * @param array<string, mixed> $manifest
     */
    private function validateDiyRenderers(ZipArchive $zip, array $manifest): void
    {
        foreach ((array) ($manifest['diy_widgets'] ?? []) as $i => $w) {
            if (!is_array($w) || !is_array($w['renderer'] ?? null)) {
                continue;
            }
            foreach ($this->profile->sourceEnds() as $end) {
                $name = $w['renderer'][$end] ?? null;
                if (!is_string($name) || $name === '' || !preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $name)) {
                    continue; // 格式错误由 PluginManifestValidator 报告，这里不重复
                }
                $path = "src/{$end}/components/diy/{$name}.vue";
                if ($zip->locateName($path) === false) {
                    throw new BusinessException(
                        "diy_widgets[{$i}].renderer.{$end} 声明的渲染器组件在 zip 内不存在：{$path}",
                        422
                    );
                }
            }
        }
        // widget 图标：新约定 src/tenant/assets/diy/，旧包回退 assets/diy/；URL 跳过
        foreach ((array) ($manifest['diy_widgets'] ?? []) as $i => $w) {
            $icon = is_array($w) ? ($w['icon'] ?? null) : null;
            if (!is_string($icon) || $icon === '' || preg_match('#^https?://#i', $icon)
                || !preg_match(PluginCodePattern::ICON_FILE, $icon)) {
                continue; // 格式错误由 PluginManifestValidator 报告
            }
            $current = PluginIconResolver::DIY_ICON_DIR . '/' . $icon;
            $legacy = PluginIconResolver::DIY_ICON_DIR_LEGACY . '/' . $icon;
            if ($zip->locateName($current) === false && $zip->locateName($legacy) === false) {
                throw new BusinessException(
                    "diy_widgets[{$i}].icon 声明的图标在 zip 内不存在：{$current}（旧包可放 {$legacy}）",
                    422
                );
            }
        }
        $this->validateDiyRendererImports($zip);
    }

    /**
     * pc.pages 声明了 component 时，zip 内必须有对应 src/pc/… 源文件。
     * 格式由 PluginManifestValidator 把关；这里只拦「声明了但没打进包」。
     *
     * @param array<string, mixed> $manifest
     */
    private function validatePcPages(ZipArchive $zip, array $manifest): void
    {
        $code = (string) ($manifest['code'] ?? '');
        foreach ((array) ($manifest['pc']['pages'] ?? []) as $i => $page) {
            $component = is_array($page) ? (string) ($page['component'] ?? '') : '';
            if ($component === '' || $code === '') {
                continue;
            }
            // component = plugins/{code}/pages/index.vue
            $prefix = 'plugins/' . $code . '/';
            if (!str_starts_with($component, $prefix)) {
                throw new BusinessException("pc.pages[{$i}].component 必须以 {$prefix} 开头", 422);
            }
            $rel = 'src/pc/' . substr($component, strlen($prefix));
            if (!str_ends_with($rel, '.vue')) {
                $rel .= '.vue';
            }
            if ($zip->locateName($rel) === false) {
                throw new BusinessException("pc.pages[{$i}].component 在 zip 内不存在：{$rel}", 422);
            }
        }
    }

    /**
     * 主包 DIY 渲染器禁止引用分包模块（@/modules/ 或相对路径逃出 diy 目录）。
     */
    private function validateDiyRendererImports(ZipArchive $zip): void
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || !str_starts_with($name, self::DIY_UNIAPP_PREFIX)) {
                continue;
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['vue', 'ts'], true)) {
                continue;
            }
            $content = $zip->getFromIndex($i);
            if ($content === false || $content === '') {
                continue;
            }
            if (!preg_match_all('/(?:from|import|require\s*\()\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
                continue;
            }
            foreach ($matches[1] as $spec) {
                if (str_starts_with($spec, '@/modules/')) {
                    throw new BusinessException(
                        "DIY 渲染器 {$name} 引用了分包 @/modules/（小程序主包不能 require 分包）。请改用主包 @/utils/request 等 facade",
                        422
                    );
                }
                if (!str_starts_with($spec, '.')) {
                    continue;
                }
                $resolved = $this->resolveZipRelative($name, $spec);
                if (!str_starts_with($resolved, self::DIY_UNIAPP_PREFIX)) {
                    throw new BusinessException(
                        "DIY 渲染器 {$name} 用相对路径引用了 diy 目录外的分包模块 {$spec}（解析为 {$resolved}）。请改用主包 facade",
                        422
                    );
                }
            }
        }
    }

    private function resolveZipRelative(string $fromFile, string $spec): string
    {
        $dir = str_replace('\\', '/', dirname($fromFile));
        $parts = $dir === '.' || $dir === '' ? [] : explode('/', $dir);
        $specPath = explode('?', $spec, 2)[0];
        foreach (explode('/', $specPath) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $seg;
        }

        return implode('/', $parts);
    }

    private function scanPhpEntries(ZipArchive $zip): void
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || !str_ends_with(strtolower($name), '.php')) {
                continue;
            }

            $content = $zip->getFromIndex($i);
            if ($content === false) {
                continue;
            }

            foreach (self::DANGEROUS_PATTERNS as $label => $pattern) {
                if (preg_match($pattern, $content)) {
                    throw new BusinessException("PHP 静态扫描命中危险函数 [{$label}]：{$name}", 422);
                }
            }
        }
    }
}
