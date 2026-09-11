<?php

/**
 * 契约检查（spec §7.1）：对运行中的服务逐条断言响应格式与关键字段，随里程碑扩充。
 *
 * 用法：php start.php start -d && php scripts/admin-contract-check.php
 * 地址：默认取 .env 的 SERVER_LISTEN 端口；可用环境变量 CONTRACT_BASE_URL 覆盖。
 * 退出码：0 = 全部通过。
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$port = parse_url((string) ($_ENV['SERVER_LISTEN'] ?? 'http://0.0.0.0:8000'), PHP_URL_PORT) ?: 8000;
$base = rtrim((string) (getenv('CONTRACT_BASE_URL') ?: "http://127.0.0.1:{$port}"), '/');

/** @var list<string> $failures */
$failures = [];
$passes = 0;

/**
 * @param list<string> $headers
 * @return array{status: int, headers: array<string, string>, body: string, json: mixed}
 */
function http(string $method, string $url, array $headers = []): array
{
    $responseHeaders = [];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return strlen($line);
        },
    ]);
    $body = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    return ['status' => $status, 'headers' => $responseHeaders, 'body' => $body, 'json' => json_decode($body, true)];
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures, $passes;
    if ($ok) {
        $passes++;
        echo "  [PASS] {$label}\n";
        return;
    }
    $failures[] = $label;
    echo "  [FAIL] {$label}" . ($detail !== '' ? " :: {$detail}" : '') . "\n";
}

function isEnvelope(mixed $json): bool
{
    return is_array($json)
        && array_keys($json) === ['code', 'message', 'data', 'timestamp']
        && is_int($json['code'])
        && is_string($json['message'])
        && is_int($json['timestamp']);
}

$api = ['Accept: application/json'];

echo "\n=== 健康检查 ===\n";
$r = http('GET', "{$base}/adminapi/health", $api);
check('HTTP 200', $r['status'] === 200, (string) $r['status']);
check('响应格式为 {code,message,data,timestamp}', isEnvelope($r['json']), $r['body']);
check('code = 200', ($r['json']['code'] ?? null) === 200);
check('data.status = ok', ($r['json']['data']['status'] ?? null) === 'ok');
check('data.version 为字符串', is_string($r['json']['data']['version'] ?? null));

echo "\n=== trace-id ===\n";
$trace = 'trace_1726000000000_abc123def';
$r = http('GET', "{$base}/adminapi/health", [...$api, "X-Trace-Id: {$trace}"]);
check('合法的入站 X-Trace-Id 原样回写', ($r['headers']['x-trace-id'] ?? '') === $trace, json_encode($r['headers']));
$r = http('GET', "{$base}/adminapi/health", [...$api, 'X-Trace-Id: bad']);
check('非法的入站 X-Trace-Id 被替换为 32 位十六进制', preg_match('/^[0-9a-f]{32}$/', $r['headers']['x-trace-id'] ?? '') === 1);

echo "\n=== 未知接口 ===\n";
$r = http('GET', "{$base}/adminapi/does-not-exist", $api);
check('HTTP 404（未知路由，与 TP8 版一致）', $r['status'] === 404, (string) $r['status']);
check('响应格式正确且 code = 404', isEnvelope($r['json']) && $r['json']['code'] === 404, $r['body']);

echo "\n=== SPA 托管 ===\n";
$r = http('GET', "{$base}/admin/system/admin");
check('/admin 深层路由返回 index.html', $r['status'] === 200 && str_contains($r['headers']['content-type'] ?? '', 'text/html'));
$r = http('GET', "{$base}/admin/favicon.ico");
check('/admin 下的静态文件按原文件返回', $r['status'] === 200 && !str_contains($r['headers']['content-type'] ?? '', 'text/html'));
$r = http('GET', "{$base}/", ['User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Mobile']);
check('/ 在移动端 UA 下 302 到 /mobile/', $r['status'] === 302 && ($r['headers']['location'] ?? '') === '/mobile/');
if (!is_file(dirname(__DIR__) . '/public/mobile/index.html')) {
    $r = http('GET', "{$base}/mobile/");
    check('未部署的 mobile 返回 404', $r['status'] === 404);
}

echo "\n通过 {$passes} 项，失败 " . count($failures) . " 项\n";
exit($failures === [] ? 0 : 1);
