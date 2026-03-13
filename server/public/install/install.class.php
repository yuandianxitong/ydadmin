<?php
/* ============================================================
 * 项目：元点Admin
 * 安装程序：系统初始化向导
 * ============================================================ */

class Installer
{
    private $rootPath;

    public function __construct()
    {
        $this->rootPath = dirname(dirname(__DIR__)) . '/';
    }

    /**
     * 检查运行环境
     */
    public function checkEnvironment()
    {
        $requirements = [
            'php_version' => [
                'name' => 'PHP版本',
                'required' => '>= 8.0.0',
                'current' => PHP_VERSION,
                'status' => version_compare(PHP_VERSION, '8.0.0', '>='),
                'type' => 'critical'
            ],
            'pdo' => [
                'name' => 'PDO扩展',
                'required' => '必需',
                'current' => extension_loaded('pdo') ? '已安装' : '未安装',
                'status' => extension_loaded('pdo'),
                'type' => 'critical'
            ],
            'pdo_mysql' => [
                'name' => 'PDO_MySQL扩展',
                'required' => '必需',
                'current' => extension_loaded('pdo_mysql') ? '已安装' : '未安装',
                'status' => extension_loaded('pdo_mysql'),
                'type' => 'critical'
            ],
            'mbstring' => [
                'name' => 'mbstring扩展',
                'required' => '必需',
                'current' => extension_loaded('mbstring') ? '已安装' : '未安装',
                'status' => extension_loaded('mbstring'),
                'type' => 'critical'
            ],
            'json' => [
                'name' => 'JSON扩展',
                'required' => '必需',
                'current' => extension_loaded('json') ? '已安装' : '未安装',
                'status' => extension_loaded('json'),
                'type' => 'critical'
            ],
            'fileinfo' => [
                'name' => 'fileinfo扩展',
                'required' => '必需',
                'current' => extension_loaded('fileinfo') ? '已安装' : '未安装',
                'status' => extension_loaded('fileinfo'),
                'type' => 'critical'
            ],
            'curl' => [
                'name' => 'cURL扩展',
                'required' => '推荐',
                'current' => extension_loaded('curl') ? '已安装' : '未安装',
                'status' => extension_loaded('curl'),
                'type' => 'recommended'
            ],
            'openssl' => [
                'name' => 'OpenSSL扩展',
                'required' => '推荐',
                'current' => extension_loaded('openssl') ? '已安装' : '未安装',
                'status' => extension_loaded('openssl'),
                'type' => 'recommended'
            ],
            'gd' => [
                'name' => 'GD扩展',
                'required' => '推荐',
                'current' => extension_loaded('gd') ? '已安装' : '未安装',
                'status' => extension_loaded('gd'),
                'type' => 'recommended'
            ]
        ];

        // 检查目录权限 - 修正路径为与public同级
        $directories = [
            'runtime' => $this->rootPath . 'runtime',
            'config' => $this->rootPath . 'config',
            'public' => $this->rootPath . 'public'
        ];

        $permissions = [];
        foreach ($directories as $name => $path) {
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);
            $readable = $exists && is_readable($path);

            $permissions[$name] = [
                'name' => $name . '目录',
                'path' => str_replace($this->rootPath, '', $path),
                'exists' => $exists,
                'writable' => $writable,
                'readable' => $readable,
                'status' => $exists && $writable && $readable
            ];
        }

        // 判断整体状态
        $criticalPassed = true;
        $warningCount = 0;

        foreach ($requirements as $req) {
            if (!$req['status']) {
                if ($req['type'] === 'critical') {
                    $criticalPassed = false;
                } else {
                    $warningCount++;
                }
            }
        }

        foreach ($permissions as $perm) {
            if (!$perm['status']) {
                $criticalPassed = false;
            }
        }

        return [
            'success' => true,
            'requirements' => $requirements,
            'permissions' => $permissions,
            'critical_passed' => $criticalPassed,
            'warning_count' => $warningCount,
            'can_continue' => true // 允许继续安装，即使有警告
        ];
    }

    /**
     * 测试数据库连接
     */
    public function testDatabase($config)
    {
        $host = $config['db_host'] ?? '';
        $port = $config['db_port'] ?? 3306;
        $database = $config['db_name'] ?? '';
        $username = $config['db_user'] ?? '';
        $password = $config['db_pass'] ?? '';

        if (empty($host) || empty($database) || empty($username)) {
            return ['success' => false, 'message' => '数据库信息不完整'];
        }

        try {
            // 先连接服务器
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // 检查数据库是否存在，不存在则创建
            $stmt = $pdo->query("SHOW DATABASES LIKE '{$database}'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("CREATE DATABASE `{$database}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }

            // 切换到目标数据库
            $pdo->exec("USE `{$database}`");

            return ['success' => true, 'message' => '数据库连接成功'];

        } catch (PDOException $e) {
            return ['success' => false, 'message' => '数据库连接失败: ' . $e->getMessage()];
        }
    }

    /**
     * 开始安装
     */
    public function startInstall($config)
    {
        try {
            $prefix = trim((string)($config['db_prefix'] ?? ''));
            if ($prefix !== '' && !preg_match('/^[a-zA-Z0-9_]+$/', $prefix)) {
                return ['success' => false, 'message' => '数据表前缀仅允许字母、数字、下划线'];
            }

            $dropExisting = !empty($config['drop_existing']);
            if ($dropExisting && $prefix === '') {
                return ['success' => false, 'message' => '清空数据表需要先填写数据表前缀（避免误删整库）'];
            }

            $_SESSION['install_config'] = $config;
            $_SESSION['install_step'] = 0;
            $_SESSION['install_total'] = 6;
            $_SESSION['install_status'] = 'running';
            $_SESSION['install_message'] = '开始安装...';

            return ['success' => true, 'message' => '安装已开始'];

        } catch (Exception $e) {
            return ['success' => false, 'message' => '安装启动失败: ' . $e->getMessage()];
        }
    }

    /**
     * 获取安装进度
     */
    public function getInstallProgress()
    {
        if (!isset($_SESSION['install_status'])) {
            return ['success' => false, 'message' => '安装未开始'];
        }

        $total = $_SESSION['install_total'] ?? 7;

        if ($_SESSION['install_status'] === 'running') {
            $this->processInstallStep();
        }

        // 在 processInstallStep 之后重新读取，确保拿到最新值
        $step = $_SESSION['install_step'] ?? 0;
        $status = $_SESSION['install_status'];
        $message = $_SESSION['install_message'] ?? '';

        $percent = ($status === 'completed')
            ? 100
            : ($total > 0 ? round(($step / $total) * 100) : 0);

        $stepDefs = [
            ['key' => 'step_0', 'name' => '更新配置文件'],
            ['key' => 'step_1', 'name' => '生成系统密钥'],
            ['key' => 'step_2', 'name' => '创建数据表'],
            ['key' => 'step_3', 'name' => '初始化数据'],
            ['key' => 'step_4', 'name' => '创建目录'],
            ['key' => 'step_5', 'name' => '完成安装'],
        ];

        return [
            'success'  => true,
            'status'   => $status,
            'step'     => $step,
            'total'    => $total,
            'percent'  => $percent,
            'message'  => $message,
            'step_key' => 'step_' . min($step, count($stepDefs) - 1),
            'steps'    => $stepDefs,
        ];
    }

    /**
     * 处理安装步骤
     */
    private function processInstallStep()
    {
        $step = $_SESSION['install_step'];
        $config = $_SESSION['install_config'];

        try {
            switch ($step) {
                case 0:
                    $this->updateEnvFile($config);
                    $_SESSION['install_message'] = '配置文件更新完成';
                    break;

                case 1:
                    $this->generateAuthKey();
                    $_SESSION['install_message'] = '系统密钥生成完成';
                    break;

                case 2:
                    $this->createDatabaseTables();
                    $_SESSION['install_message'] = '数据表创建完成';
                    break;

                case 3:
                    $this->insertInitialData($config);
                    $_SESSION['install_message'] = '初始化数据完成';
                    break;

                case 4:
                    $this->createDirectories();
                    $_SESSION['install_message'] = '目录创建完成';
                    break;

                case 5:
                    $this->createInstallLock();
                    $_SESSION['install_message'] = '安装完成';
                    $_SESSION['install_status'] = 'completed';
                    break;

                default:
                    $_SESSION['install_status'] = 'completed';
                    return;
            }

            $_SESSION['install_step']++;

        } catch (Exception $e) {
            $_SESSION['install_status'] = 'error';
            $_SESSION['install_message'] = '安装失败: ' . $e->getMessage();
        }
    }

    /**
     * 更新环境配置文件
     */
    private function updateEnvFile($config)
    {
        $envFile = $this->rootPath . '.env';
        $envExample = $this->rootPath . '.example.env';

        // 如果.env不存在，复制.example.env
        if (!file_exists($envFile) && file_exists($envExample)) {
            copy($envExample, $envFile);
        }

        $envContent = file_exists($envFile) ? file_get_contents($envFile) : '';

        // ---- 数据库配置写入 [DB] 段 ----
        // ThinkPHP 的 env() 通过 parse_ini_file 读取，
        // [DB] 段下的 NAME = xxx 会被解析为 DB_NAME，匹配 env('DB_NAME')
        $dbSection = [
            'TYPE'    => 'mysql',
            'HOST'    => $config['db_host'],
            'NAME'    => $config['db_name'],
            'USER'    => $config['db_user'],
            'PASS'    => $config['db_pass'],
            'PORT'    => $config['db_port'],
            'CHARSET' => 'utf8mb4',
            'PREFIX'  => trim((string)($config['db_prefix'] ?? ''))
        ];

        // 如果已有 [DB] 段，替换整段；否则追加
        $dbBlock = "[DB]\n";
        foreach ($dbSection as $k => $v) {
            $dbBlock .= "{$k} = {$v}\n";
        }

        if (preg_match('/^\[DB\]\s*$/m', $envContent)) {
            // 替换 [DB] 段（从 [DB] 到下一个 [SECTION] 或文件末尾）
            $envContent = preg_replace(
                '/^\[DB\]\s*\n(?:(?!\[).+\n?)*/m',
                $dbBlock,
                $envContent
            );
        } else {
            $envContent = rtrim($envContent) . "\n\n" . $dbBlock;
        }

        // ---- 顶层配置（写在所有 [SECTION] 之前）----
        $topLevel = [];
        if (isset($config['admin_frontend_url'])) {
            $topLevel['ADMIN_FRONTEND_URL'] = trim((string)$config['admin_frontend_url']);
        }

        foreach ($topLevel as $key => $value) {
            if (preg_match("/^{$key}\s*=/m", $envContent)) {
                $envContent = preg_replace("/^{$key}\s*=.*$/m", "{$key} = {$value}", $envContent);
            } else {
                // 插入到第一个 [SECTION] 之前
                if (preg_match('/^\[/m', $envContent, $m, PREG_OFFSET_CAPTURE)) {
                    $pos = $m[0][1];
                    $envContent = substr($envContent, 0, $pos) . "{$key} = {$value}\n\n" . substr($envContent, $pos);
                } else {
                    $envContent .= "\n{$key} = {$value}";
                }
            }
        }

        file_put_contents($envFile, $envContent);
    }

    /**
     * 创建数据表
     */
    private function createDatabaseTables()
    {
        $config = $_SESSION['install_config'];
        $prefix = trim((string)($config['db_prefix'] ?? ''));
        $dropExisting = !empty($config['drop_existing']);

        $pdo = $this->createPdo($config);

        // Installer ships with its own schema file under public/install/data
        $schemaFile = INSTALL_PATH . 'data/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception('数据库结构文件不存在: public/install/data/schema.sql');
        }

        // Guard against re-install on a dirty database.
        $existing = $this->listTablesWithPrefix($pdo, $prefix);
        if (!empty($existing)) {
            if ($dropExisting) {
                $this->dropTablesWithPrefix($pdo, $prefix);
            } else {
                $sample = array_slice($existing, 0, 8);
                throw new Exception('检测到数据库中已存在同前缀的数据表（例如：' . implode(', ', $sample) . '）。请更换空数据库，或在安装选项中勾选“清空同前缀数据表后再安装”。');
            }
        }

        $this->executeSqlFile($pdo, $schemaFile, $prefix);

        // Sanity check: ensure core tables exist after schema import.
        $required = ['admins', 'roles', 'system_configs'];
        $missing = [];
        foreach ($required as $table) {
            if (!$this->tableExists($pdo, $prefix . $table)) {
                $missing[] = $prefix . $table;
            }
        }
        if (!empty($missing)) {
            throw new Exception('数据库结构导入不完整，缺少数据表: ' . implode(', ', $missing));
        }
    }

    /**
     * 插入初始数据
     */
    private function insertInitialData($config)
    {
        $pdo = $this->createPdo($config);

        $prefix = trim((string)($config['db_prefix'] ?? ''));
        $initFile = INSTALL_PATH . 'data/init.sql';
        if (!file_exists($initFile)) {
            throw new Exception('初始化数据文件不存在: public/install/data/init.sql');
        }
        $this->executeSqlFile($pdo, $initFile, $prefix);

        $importSeed = !empty($config['import_seed']);
        if ($importSeed) {
            $demoFile = INSTALL_PATH . 'data/demo.sql';
            if (file_exists($demoFile)) {
                $this->executeSqlFile($pdo, $demoFile, $prefix);
            }
            // demo.sql 不存在时静默跳过，不影响安装
        }

        $this->ensureBaseRole($pdo, $prefix);
        $this->upsertAdminAccount($pdo, $config, $prefix);
        $this->upsertSystemConfig($pdo, $config, $prefix);
    }

    private function createPdo(array $config, bool $withDb = true): PDO
    {
        $host = (string)($config['db_host'] ?? '');
        $port = (string)($config['db_port'] ?? 3306);
        $dbName = (string)($config['db_name'] ?? '');
        $user = (string)($config['db_user'] ?? '');
        $pass = (string)($config['db_pass'] ?? '');

        $dsn = $withDb
            ? "mysql:host={$host};dbname={$dbName};port={$port};charset=utf8mb4"
            : "mysql:host={$host};port={$port};charset=utf8mb4";

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Some environments ignore DSN charset; force it.
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return $pdo;
    }

    private function ensureBaseRole(PDO $pdo, string $prefix)
    {
        $table = $this->wrapTable('roles', $prefix);
        $exists = $this->recordExists($pdo, $table, 'id', 1);

        if ($exists) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("INSERT INTO {$table} (id, name, title, description, data_scope, is_system, status, sort, created_at, updated_at) VALUES (1, ?, ?, ?, 1, 1, 1, 0, ?, ?)");
        $stmt->execute(['super_admin', '超级管理员', '系统超级管理员，拥有所有权限', $now, $now]);
    }

    private function upsertAdminAccount(PDO $pdo, array $config, string $prefix)
    {
        $username = trim((string)($config['admin_username'] ?? ''));
        $password = (string)($config['admin_password'] ?? '');
        if ($username === '' || $password === '') {
            throw new Exception('管理员账号或密码为空');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $email = trim((string)($config['admin_email'] ?? ''));
        $nickname = trim((string)($config['admin_nickname'] ?? $username));
        $now = date('Y-m-d H:i:s');

        $adminTable = $this->wrapTable('admins', $prefix);
        $adminRoleTable = $this->wrapTable('admin_roles', $prefix);

        $exists = $this->recordExists($pdo, $adminTable, 'id', 1);
        if ($exists) {
            $stmt = $pdo->prepare("UPDATE {$adminTable} SET username = ?, password = ?, nickname = ?, email = ?, status = 1, updated_at = ? WHERE id = 1");
            $stmt->execute([$username, $passwordHash, $nickname, $email, $now]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO {$adminTable} (id, username, email, password, nickname, status, created_at, updated_at) VALUES (1, ?, ?, ?, ?, 1, ?, ?)");
            $stmt->execute([$username, $email, $passwordHash, $nickname, $now, $now]);
        }

        $roleLinkExists = $this->recordExists($pdo, $adminRoleTable, 'admin_id', 1, 'role_id', 1);
        if (!$roleLinkExists) {
            $stmt = $pdo->prepare("INSERT INTO {$adminRoleTable} (admin_id, role_id, created_at, updated_at) VALUES (1, 1, ?, ?)");
            $stmt->execute([$now, $now]);
        }
    }

    private function upsertSystemConfig(PDO $pdo, array $config, string $prefix)
    {
        $table = $this->wrapTable('system_configs', $prefix);
        $siteName = trim((string)($config['site_name'] ?? ''));
        $siteUrl = trim((string)($config['site_url'] ?? ''));
        $now = date('Y-m-d H:i:s');

        if ($siteName !== '') {
            $stmt = $pdo->prepare("INSERT INTO {$table} (`config_key`, `config_value`, `config_group`, `config_type`, `config_name`, `config_desc`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES ('site_name', ?, 'basic', 'string', '站点名称', '网站名称显示在浏览器标题栏', 1, 1, ?, ?) ON DUPLICATE KEY UPDATE `config_value` = VALUES(`config_value`), `updated_at` = VALUES(`updated_at`)");
            $stmt->execute([$siteName, $now, $now]);
        }

        if ($siteUrl !== '') {
            $stmt = $pdo->prepare("INSERT INTO {$table} (`config_key`, `config_value`, `config_group`, `config_type`, `config_name`, `config_desc`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES ('site_url', ?, 'basic', 'string', '站点网址', '网站访问地址', 2, 1, ?, ?) ON DUPLICATE KEY UPDATE `config_value` = VALUES(`config_value`), `updated_at` = VALUES(`updated_at`)");
            $stmt->execute([$siteUrl, $now, $now]);
        }
    }

    /**
     * 创建必要目录
     */
    private function createDirectories()
    {
        $directories = [
            'runtime/cache',
            'runtime/log',
            'runtime/temp',
            'public/storage',
            'public/storage/uploads',
            'public/storage/uploads/images',
            'public/storage/uploads/files',
            'public/storage/uploads/docs'
        ];

        foreach ($directories as $dir) {
            $path = $this->rootPath . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }

        // 创建安全文件
        $indexContent = "<?php\n// Silence is golden.";
        foreach ($directories as $dir) {
            $indexFile = $this->rootPath . $dir . '/index.php';
            if (!file_exists($indexFile)) {
                file_put_contents($indexFile, $indexContent);
            }
        }
    }

    /**
     * 生成应用密钥
     */
    private function generateAuthKey()
    {
        $this->getAuthKey(true);
    }

    /**
     * 创建安装锁定文件
     */
    private function createInstallLock()
    {
        $lockFile = $this->rootPath . 'config/install.lock';
        $lockContent = [
            'install_time' => date('Y-m-d H:i:s'),
            'version' => '1.0.0',
            'installer_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];

        // 确保config目录存在
        $configDir = dirname($lockFile);
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        file_put_contents($lockFile, json_encode($lockContent, JSON_PRETTY_PRINT));

        // If the project ran before installation (or DB_PREFIX changed during install),
        // ThinkPHP may still use cached config/route/schema files under runtime/.
        // Clear runtime cache/temp to ensure the new .env values take effect immediately.
        $this->clearRuntimeCaches();
    }

    private function clearRuntimeCaches(): void
    {
        $targets = [
            $this->rootPath . 'runtime/cache',
            $this->rootPath . 'runtime/temp',
        ];

        foreach ($targets as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $this->deleteDirectoryChildren($dir, ['index.php']);
            // Keep runtime dirs safe from directory listing.
            $indexFile = rtrim($dir, '/') . '/index.php';
            if (!file_exists($indexFile)) {
                file_put_contents($indexFile, "<?php\n// Silence is golden.");
            }
        }
    }

    private function deleteDirectoryChildren(string $dir, array $keepFiles = []): void
    {
        $items = @scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (in_array($item, $keepFiles, true)) {
                continue;
            }
            $path = rtrim($dir, '/') . '/' . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
                continue;
            }
            @unlink($path);
        }
    }

    /**
     * 删除安装程序
     */
    public function deleteInstaller()
    {
        try {
            $installDir = __DIR__;
            $this->deleteDirectory($installDir);
            return ['success' => true, 'message' => '安装程序已删除'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => '删除失败: ' . $e->getMessage()];
        }
    }

    /**
     * 清除会话数据
     */
    public function clearSession()
    {
        // 清除安装相关的session数据
        $keys = ['install_config', 'install_step', 'install_total', 'install_status', 'install_message', 'install_auth_key'];
        foreach ($keys as $key) {
            if (isset($_SESSION[$key])) {
                unset($_SESSION[$key]);
            }
        }
        return ['success' => true, 'message' => '会话数据已清除'];
    }

    /**
     * 递归删除目录
     */
    private function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * 获取系统信息
     */
    public function getSystemInfo()
    {
        return [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'os' => PHP_OS,
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size')
        ];
    }

    private function executeSqlFile(PDO $pdo, string $sqlFile, string $prefix = '')
    {
        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            throw new Exception('SQL文件读取失败: ' . $sqlFile);
        }

        $sql = $this->applyTablePrefix($sql, $prefix);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        $lines = preg_split("/\r\n|\n|\r/", $sql);
        $filtered = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '#') === 0) {
                continue;
            }
            $filtered[] = $line;
        }
        $sql = implode("\n", $filtered);
        $statements = $this->splitSqlStatements($sql);

        $i = 0;
        foreach ($statements as $statement) {
            $i++;
            if ($statement !== '') {
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    $snippet = preg_replace('/\s+/', ' ', trim($statement));
                    $snippet = mb_substr($snippet, 0, 260, 'UTF-8');
                    throw new Exception('SQL执行失败（第 ' . $i . ' 条）：' . $e->getMessage() . '；SQL片段：' . $snippet);
                }
            }
        }
    }

    private function splitSqlStatements(string $sql): array
    {
        // Split by semicolon, but never inside quoted strings/backticks.
        $statements = [];
        $buffer = '';
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;
        $escape = false;

        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];

            if ($escape) {
                $buffer .= $ch;
                $escape = false;
                continue;
            }

            if (($inSingle || $inDouble) && $ch === '\\') {
                $buffer .= $ch;
                $escape = true;
                continue;
            }

            if (!$inDouble && !$inBacktick && $ch === "'") {
                $inSingle = !$inSingle;
                $buffer .= $ch;
                continue;
            }
            if (!$inSingle && !$inBacktick && $ch === '"') {
                $inDouble = !$inDouble;
                $buffer .= $ch;
                continue;
            }
            if (!$inSingle && !$inDouble && $ch === '`') {
                $inBacktick = !$inBacktick;
                $buffer .= $ch;
                continue;
            }

            if (!$inSingle && !$inDouble && !$inBacktick && $ch === ';') {
                $stmt = trim($buffer);
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $ch;
        }

        $stmt = trim($buffer);
        if ($stmt !== '') {
            $statements[] = $stmt;
        }

        return $statements;
    }

    private function applyTablePrefix(string $sql, string $prefix): string
    {
        $prefix = trim($prefix);
        if ($prefix === '') {
            return $sql;
        }

        $patterns = [
            '/(?P<keyword>CREATE\s+TABLE\s+)(?P<optional>IF\s+NOT\s+EXISTS\s+)?`?(?P<name>[a-zA-Z0-9_]+)`?/i',
            '/(?P<keyword>INSERT\s+INTO\s+)`?(?P<name>[a-zA-Z0-9_]+)`?/i',
            // Anchor DML statements to statement start so we don't touch "ON UPDATE/ON DELETE ... CASCADE" in DDL.
            '/(?P<keyword>^\s*UPDATE\s+)`?(?P<name>[a-zA-Z0-9_]+)`?/im',
            '/(?P<keyword>^\s*DELETE\s+FROM\s+)`?(?P<name>[a-zA-Z0-9_]+)`?/im',
            // DML subqueries / joins
            '/(?P<keyword>\bFROM\s+)`?(?P<name>[a-zA-Z0-9_]+)`?/i',
            '/(?P<keyword>\bJOIN\s+)`?(?P<name>[a-zA-Z0-9_]+)`?/i',
            '/(?P<keyword>ALTER\s+TABLE\s+)`?(?P<name>[a-zA-Z0-9_]+)`?/i',
            '/(?P<keyword>DROP\s+TABLE\s+)(?P<optional>IF\s+EXISTS\s+)?`?(?P<name>[a-zA-Z0-9_]+)`?/i',
            '/(?P<keyword>RENAME\s+TABLE\s+)`?(?P<name>[a-zA-Z0-9_]+)`?/i',
            '/(?P<keyword>TRUNCATE\s+TABLE\s+)`?(?P<name>[a-zA-Z0-9_]+)`?/i',
            '/(?P<keyword>REFERENCES\s+)`?(?P<name>[a-zA-Z0-9_]+)`?/i'
        ];

        foreach ($patterns as $pattern) {
            $sql = preg_replace_callback($pattern, function ($matches) use ($prefix) {
                $keyword = $matches['keyword'];
                $optional = $matches['optional'] ?? '';
                $name = $matches['name'];
                if (strpos($name, $prefix) === 0) {
                    return $keyword . $optional . '`' . $name . '`';
                }
                return $keyword . $optional . '`' . $prefix . $name . '`';
            }, $sql);
        }

        return $sql;
    }

    private function wrapTable(string $name, string $prefix): string
    {
        return '`' . $prefix . $name . '`';
    }

    private function recordExists(PDO $pdo, string $table, string $field, $value, ?string $field2 = null, $value2 = null): bool
    {
        if ($field2 !== null) {
            $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$field} = ? AND {$field2} = ? LIMIT 1");
            $stmt->execute([$value, $value2]);
        } else {
            $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$field} = ? LIMIT 1");
            $stmt->execute([$value]);
        }
        return (bool)$stmt->fetchColumn();
    }

    private function tableExists(PDO $pdo, string $tableName): bool
    {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$tableName]);
        return (bool)$stmt->fetchColumn();
    }

    private function listTablesWithPrefix(PDO $pdo, string $prefix): array
    {
        // Empty prefix is too dangerous to operate on; treat as "unknown".
        if (trim($prefix) === '') {
            return [];
        }

        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$prefix . '%']);
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        return array_values(array_filter(array_map(static fn($r) => (string)($r[0] ?? ''), $rows)));
    }

    private function dropTablesWithPrefix(PDO $pdo, string $prefix): void
    {
        $tables = $this->listTablesWithPrefix($pdo, $prefix);
        if (empty($tables)) {
            return;
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $t) {
            $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', $t) . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    private function getAuthKey(bool $forceGenerate = false): string
    {
        if (!$forceGenerate && !empty($_SESSION['install_auth_key'])) {
            return (string)$_SESSION['install_auth_key'];
        }

        $envFile = $this->rootPath . '.env';
        $envContent = file_exists($envFile) ? file_get_contents($envFile) : '';
        $authKey = '';

        if (preg_match("/^APP_KEY\s*=\s*(.*)$/m", $envContent, $matches)) {
            $authKey = trim($matches[1]);
        }

        if ($authKey === '' || $forceGenerate) {
            $authKey = bin2hex(random_bytes(32));
            if (preg_match("/^APP_KEY\s*=/m", $envContent)) {
                $envContent = preg_replace("/^APP_KEY\s*=.*$/m", "APP_KEY = {$authKey}", $envContent);
            } else {
                // 插入到第一个 [SECTION] 之前，确保在顶层
                if (preg_match('/^\[/m', $envContent, $m, PREG_OFFSET_CAPTURE)) {
                    $pos = $m[0][1];
                    $envContent = substr($envContent, 0, $pos) . "APP_KEY = {$authKey}\n\n" . substr($envContent, $pos);
                } else {
                    $envContent .= "\nAPP_KEY = {$authKey}";
                }
            }
            // 同时生成 JWT_SECRET
            $jwtSecret = bin2hex(random_bytes(32));
            if (preg_match("/^JWT_SECRET\s*=/m", $envContent)) {
                $envContent = preg_replace("/^JWT_SECRET\s*=.*$/m", "JWT_SECRET = {$jwtSecret}", $envContent);
            } else {
                // 在 APP_KEY 之后插入
                $envContent = preg_replace("/^(APP_KEY\s*=.*$)/m", "$1\nJWT_SECRET = {$jwtSecret}", $envContent);
            }

            file_put_contents($envFile, $envContent);
        }

        $_SESSION['install_auth_key'] = $authKey;
        return $authKey;
    }
}
