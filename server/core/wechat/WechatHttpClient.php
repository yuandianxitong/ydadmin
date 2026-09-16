<?php

declare(strict_types=1);

namespace core\wechat;

use core\wechat\exception\WechatApiException;
use core\wechat\exception\WechatUnavailableException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;

/**
 * 微信服务端 API 的最薄 HTTP 层（M6a spec §3.1，不引入 easywechat，见 spec §1.4）。
 *
 * 分类：
 *   - 连接失败 / 超时 / 非 200 / 应答非 JSON 对象 → WechatUnavailableException
 *   - JSON 里 errcode 非 0                       → WechatApiException（errcode、errmsg 另存）
 *   - 其余                                        → 返回解码后的数组
 *
 * 不泄漏：微信的鉴权参数（secret、js_code、access_token）走查询串，Guzzle 异常消息会带完整 URL。
 * 所以异常消息只写接口名与状态，底层 Guzzle 异常不挂成 previous，任何地方打印异常链都不会带出密钥。
 *
 * 超时每次请求现读 config('wechat.*')：本类是容器单例，构造时固化会让改配置后需要重启才生效。
 * 可注入 HandlerStack 供离线测试（与 core\payment\driver\AlipayDriver 同一做法）。
 */
final class WechatHttpClient
{
    public const BASE_URI = 'https://api.weixin.qq.com/';

    /** 接口路径只允许「小写字母、数字、下划线、连字符」分段：它拼进 base_uri，不能被带到别的主机或路径 */
    private const API_PATTERN = '#^[a-z0-9_-]+(/[a-z0-9_-]+)*$#';

    private readonly Client $http;

    public function __construct(?HandlerStack $handler = null)
    {
        $options = [
            'base_uri'    => self::BASE_URI,
            'http_errors' => false,
        ];
        if ($handler !== null) {
            $options['handler'] = $handler;
        }
        $this->http = new Client($options);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     * @throws WechatApiException|WechatUnavailableException
     */
    public function get(string $api, array $query): array
    {
        return $this->send('GET', $api, ['query' => $query]);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     * @throws WechatApiException|WechatUnavailableException
     */
    public function postJson(string $api, array $query, array $body): array
    {
        try {
            $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new WechatUnavailableException("微信接口 {$api} 请求体无法编码");
        }

        return $this->send('POST', $api, [
            'query'   => $query,
            'body'    => $json,
            'headers' => ['Content-Type' => 'application/json'],
        ]);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function send(string $method, string $api, array $options): array
    {
        if (preg_match(self::API_PATTERN, $api) !== 1) {
            throw new \InvalidArgumentException('非法的微信接口路径');
        }

        $options['connect_timeout'] = self::seconds('wechat.connect_timeout', 5.0);
        $options['timeout'] = self::seconds('wechat.timeout', 10.0);

        try {
            $response = $this->http->request($method, $api, $options);
        } catch (GuzzleException $e) {
            throw new WechatUnavailableException("微信接口 {$api} 请求失败（" . $e::class . '）');
        }

        $status = $response->getStatusCode();
        if ($status !== 200) {
            throw new WechatUnavailableException("微信接口 {$api} 应答 HTTP {$status}");
        }

        try {
            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new WechatUnavailableException("微信接口 {$api} 应答不是合法 JSON");
        }
        if (!is_array($data)) {
            throw new WechatUnavailableException("微信接口 {$api} 应答结构非法");
        }

        $errcode = self::errcode($data['errcode'] ?? 0);
        if ($errcode !== 0) {
            throw new WechatApiException($api, $errcode, is_string($data['errmsg'] ?? null) ? $data['errmsg'] : '');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /** 微信的 errcode 一般是整数，个别接口给数字字符串；非数字一律按失败（-1）处理，不能当成功放过。 */
    private static function errcode(mixed $raw): int
    {
        if (is_int($raw)) {
            return $raw;
        }
        if (is_string($raw) && preg_match('/^-?\d+$/', $raw) === 1) {
            return (int) $raw;
        }

        return -1;
    }

    private static function seconds(string $key, float $default): float
    {
        $value = config($key, $default);
        $seconds = is_numeric($value) ? (float) $value : $default;

        return $seconds > 0 ? $seconds : $default;
    }
}
