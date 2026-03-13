<?php
/* ============================================================
 * 项目：元点Admin
 * 安装程序：系统初始化向导
 * ============================================================ */
// --- PHP Bootstrap ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

session_start();

define('INSTALL_PATH', __DIR__ . '/');
define('ROOT_PATH', dirname(dirname(__DIR__)) . '/');

function install_env(string $key, ?string $default = null): ?string {
    $envFile = ROOT_PATH . '.env';
    if (!is_file($envFile)) return $default;
    $content = file_get_contents($envFile);
    if ($content === false) return $default;
    if (!preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*(.*?)\s*$/m', $content, $m)) return $default;
    $val = trim($m[1], "\"'");
    return $val !== '' ? $val : $default;
}

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// 已安装：AJAX 请求正常返回 JSON，页面请求跳转后台
if (file_exists(ROOT_PATH . 'config/install.lock') && !$isAjax) {
    header('Location: ' . (install_env('ADMIN_FRONTEND_URL', '/admin') ?: '/admin'));
    exit;
}

$step = $_GET['step'] ?? 'license';
$validSteps = ['license', 'environment', 'config', 'install', 'complete'];
if (!in_array($step, $validSteps)) $step = 'license';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($isAjax || isset($_POST['action']))) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        require_once 'install.class.php';
        $installer = new Installer();
        $action = $_POST['action'] ?? '';
        $response = null;

        switch ($action) {
            case 'check_environment': $response = $installer->checkEnvironment(); break;
            case 'test_database': $response = $installer->testDatabase($_POST); break;
            case 'start_install': $response = $installer->startInstall($_POST); break;
            case 'get_install_progress': $response = $installer->getInstallProgress(); break;
            case 'delete_installer': $response = $installer->deleteInstaller(); break;
            case 'clear_session': $response = $installer->clearSession(); break;
            default: $response = ['success' => false, 'message' => '未知操作'];
        }
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false,
            'message' => '服务器错误: ' . $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
// --- End PHP Bootstrap ---
?>
<!DOCTYPE html>
<html lang="zh-CN" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?= htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\') . '/', ENT_QUOTES, 'UTF-8') ?>">
    <title>元点Admin 安装向导</title>
    
    <!-- Fonts and Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./libs/font-awesome/css/font-awesome.min.css">

    <!-- Styles -->
    <link href="./libs/daisyui/daisyui.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="./css/modern.css">
</head>

<body class="min-h-screen">
    <div id="toast-container"></div>

    <!-- Hero Header -->
    <div class="install-hero">
        <div class="install-hero-inner container mx-auto px-4 max-w-4xl">
            <header class="text-center mb-8">
                <h1 class="text-4xl font-bold text-white mb-2">元点Admin 安装向导</h1>
                <p class="text-lg" style="color:rgba(255,255,255,.7)">简单几步，轻松部署您的通用后台管理系统</p>
            </header>

            <!-- Step Indicator -->
            <ul class="steps w-full mb-8">
            <?php
            $stepsConfig = [
                'license'     => ['title' => '许可协议'],
                'environment' => ['title' => '环境检测'],
                'config'      => ['title' => '参数配置'],
                'install'     => ['title' => '执行安装'],
                'complete'    => ['title' => '完成'],
            ];
            $stepKeys = array_keys($stepsConfig);
            $currentIndex = array_search($step, $stepKeys);
            
            foreach ($stepsConfig as $key => $info) {
                $index = array_search($key, $stepKeys);
                $statusClass = '';
                if ($index < $currentIndex) {
                    $statusClass = 'step-primary step-completed';
                } elseif ($index === $currentIndex) {
                    $statusClass = 'step-primary step-current';
                }
                echo "<li class=\"step {$statusClass}\">{$info['title']}</li>";
            }
            ?>
        </ul>
        </div>
        <!-- Wave transition -->
        <div class="install-hero-wave">
            <svg viewBox="0 0 1440 60" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0,0 C360,56 720,56 1080,28 C1260,14 1380,2 1440,0 L1440,60 L0,60 Z" fill="#f7f8fb"/>
            </svg>
        </div>
    </div>

    <!-- Main Container -->
    <div class="container mx-auto px-4 max-w-4xl install-body">

        <!-- Main Content Area -->
        <main class="bg-white p-6 md:p-10 rounded-2xl shadow-lg">
            <?php
            $stepFile = "steps/{$step}.php";
            if (file_exists($stepFile)) {
                include $stepFile;
            } else {
                echo '<div class="notice notice--error"><i class="fa fa-exclamation-triangle"></i><div><div class="notice-title">错误</div><div class="notice-text">安装步骤文件 ' . htmlspecialchars($stepFile) . ' 不存在。</div></div></div>';
            }
            ?>
        </main>

        <!-- Footer -->
        <footer class="text-center mt-8 text-sm text-slate-500">
            <p>&copy; <?= date('Y') ?> 元点系统 | 元点Admin 通用后台管理框架</p>
        </footer>
    </div>

    <!-- Global JS Helper -->
    <script src="./js/install.js"></script>
</body>
</html>
