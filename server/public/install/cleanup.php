<?php
/* ============================================================
 * 项目：元点Admin
 * 安装程序：系统初始化向导
 * ============================================================ */
class PostInstallCleanup
{
    public function run()
    {
        $this->optimizeComposerAutoloader();
        $this->setProperPermissions();
        $this->clearCache();
        $this->generateSecurityFiles();

        echo "清理和优化完成！\n";
    }

    /**
     * 优化Composer自动加载器
     */
    private function optimizeComposerAutoloader()
    {
        if (file_exists('../../composer.json')) {
            exec('cd ../.. && composer dump-autoload -o');
            echo "Composer自动加载器已优化\n";
        }
    }

    /**
     * 设置正确的目录权限
     */
    private function setProperPermissions()
    {
        $directories = [
            '../../runtime',
            '../../public/upload',
            '../../config'
        ];

        foreach ($directories as $dir) {
            if (is_dir($dir)) {
                chmod($dir, 0755);
                echo "已设置 {$dir} 目录权限\n";
            }
        }
    }

    /**
     * 清除缓存
     */
    private function clearCache()
    {
        $cacheDir = '../../runtime/cache';
        if (is_dir($cacheDir)) {
            $this->deleteDirectory($cacheDir);
            mkdir($cacheDir, 0755, true);
            echo "已清除缓存\n";
        }
    }

    /**
     * 生成安全文件
     */
    private function generateSecurityFiles()
    {
        // 创建 .htaccess 文件
        $htaccess = <<<'EOT'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ index.php/$1 [QSA,PT,L]
</IfModule>

# 禁止访问敏感文件
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

<Files "*.lock">
    Order allow,deny  
    Deny from all
</Files>

# 安全头部
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
</IfModule>
EOT;

        file_put_contents('../../public/.htaccess', $htaccess);
        echo "已生成安全配置文件\n";
    }

    private function deleteDirectory($dir)
    {
        if (!is_dir($dir)) return;

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}

// 如果是命令行运行
if (php_sapi_name() === 'cli') {
    $cleanup = new PostInstallCleanup();
    $cleanup->run();
}
