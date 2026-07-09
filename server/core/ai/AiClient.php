<?php
declare(strict_types=1);

namespace core\ai;

/**
 * AI 引擎 HTTP 客户端（薄实现：curl + SSE，无第三方依赖）
 */
class AiClient
{
    public function __construct(
        protected string $endpoint,
        protected ?string $token = null,
        protected int $timeout = 300,
    ) {
        $this->endpoint = rtrim($endpoint, '/');
    }

    /**
     * 解析 SSE 文本为事件数组（纯函数，便于单测）
     */
    public static function parseSseBuffer(string $raw): array
    {
        $events = [];
        $currentEvent = '';
        foreach (explode("\n", $raw) as $line) {
            if (str_starts_with($line, 'event:')) {
                $currentEvent = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $decoded = json_decode(trim(substr($line, 5)), true);
                if (is_array($decoded) && $currentEvent !== '') {
                    $events[] = ['event' => $currentEvent, 'data' => $decoded];
                }
            }
        }
        return $events;
    }

    public function generate(string $instruction, string $projectId, array $layers, array $schemaInput, ?callable $onChunk = null): array
    {
        $payload = [
            'instruction'  => $instruction,
            'project_id'   => $projectId,
            'framework'    => 'php_thinkphp',
            'layers'       => $layers,
            'schema_input' => $schemaInput,
        ];
        $buffer = '';
        $raw = $this->post('/api/v1/generate', $payload, function (string $piece) use (&$buffer, $onChunk) {
            $buffer .= $piece;
            if ($onChunk) {
                // 即时回放本次收到的 chunk 事件内容
                foreach (self::parseSseBuffer($piece) as $ev) {
                    if ($ev['event'] === 'chunk' && isset($ev['data']['content'])) {
                        $onChunk($ev['data']['content']);
                    }
                }
            }
        });
        foreach (self::parseSseBuffer($raw) as $ev) {
            if ($ev['event'] === 'error') {
                throw new AiClientException('引擎错误：' . ($ev['data']['message'] ?? '未知'));
            }
            if ($ev['event'] === 'done') {
                return $ev['data'];
            }
        }
        throw new AiClientException('SSE 流结束但未收到 done 事件（可能被截断）');
    }

    public function feedback(string $generationId, string $action): void
    {
        $this->post('/api/v1/feedback', ['generation_id' => $generationId, 'action' => $action]);
    }

    protected function post(string $path, array $payload, ?callable $onWrite = null): string
    {
        $headers = ['Content-Type: application/json'];
        if ($this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        $collected = '';
        $ch = curl_init($this->endpoint . $path);
        curl_setopt_array($ch, [
            CURLOPT_POST          => true,
            CURLOPT_POSTFIELDS    => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER    => $headers,
            CURLOPT_TIMEOUT       => $this->timeout,
            CURLOPT_NOPROXY       => '127.0.0.1,localhost',
            CURLOPT_WRITEFUNCTION => function ($ch, string $piece) use (&$collected, $onWrite): int {
                $collected .= $piece;
                if ($onWrite) {
                    $onWrite($piece);
                }
                return strlen($piece);
            },
        ]);
        $ok = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($ok === false || $errno !== 0) {
            throw new AiClientException('无法连接 AI 引擎（' . curl_strerror($errno) . '），请运行 php think yd:ai:doctor 排查');
        }
        if ($status !== 200) {
            throw new AiClientException("AI 引擎返回 HTTP {$status}：" . mb_substr($collected, 0, 200));
        }
        return $collected;
    }
}
