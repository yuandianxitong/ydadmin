<?php

declare(strict_types=1);

namespace tests\Support\Payment;

use core\payment\config\AlipayConfig;
use GuzzleHttp\Psr7\Response;

/**
 * 支付宝网关替身：按开放平台格式组装「真实签名」的应答与回调参数，
 * 让 AlipayDriver 的验签逻辑在测试里跑的是真路径而不是被跳过。
 */
final class AlipayStub
{
    public static function config(string $appPrivatePem, string $alipayPublicPem, bool $sandbox = false): AlipayConfig
    {
        return new AlipayConfig('2021000000000001', $appPrivatePem, $alipayPublicPem, $sandbox, 5.0, 10.0);
    }

    /**
     * {"<method>_response":{...},"sign":"..."}；$node 以 code=10000 或 40004 等形式给出。
     *
     * @param array<string, mixed> $node
     */
    public static function response(string $method, array $node, string $alipayPrivatePem, bool $sign = true, int $status = 200): Response
    {
        $nodeName = str_replace('.', '_', $method) . '_response';
        $json = self::json($node);
        $body = '{"' . $nodeName . '":' . $json;
        if ($sign) {
            $body .= ',"sign":"' . self::sign($json, $alipayPrivatePem) . '"';
        }

        return new Response($status, ['Content-Type' => 'text/html;charset=utf-8'], $body . '}');
    }

    /**
     * sign 字段排在响应节点**之前**、且节点内含空白与嵌套对象——驱动必须按括号配对截取原文，
     * 不能照 EasySDK 用「最后一个 "sign" 的位置」倒推节点结尾。
     *
     * @param array<string, mixed> $node
     */
    public static function signBefore(string $method, array $node, string $alipayPrivatePem): Response
    {
        $nodeName = str_replace('.', '_', $method) . '_response';
        $json = str_replace(',', ', ', self::json($node));
        $body = '{"sign":"' . self::sign($json, $alipayPrivatePem) . '", "' . $nodeName . '" : ' . $json . '}';

        return new Response(200, ['Content-Type' => 'text/html;charset=utf-8'], $body);
    }

    /**
     * 回调表单签名：去掉 sign、sign_type，按键排序拼 k=v&…（空值保留）。
     *
     * @param array<string, string> $params
     * @return array<string, string>
     */
    public static function signNotify(array $params, string $alipayPrivatePem): array
    {
        $params['sign'] = self::sign(self::signContent($params), $alipayPrivatePem);
        $params['sign_type'] = 'RSA2';

        return $params;
    }

    /** @param array<string, string> $params */
    public static function signContent(array $params): string
    {
        unset($params['sign'], $params['sign_type']);
        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = $key . '=' . $value;
        }

        return implode('&', $pairs);
    }

    public static function sign(string $content, string $privatePem): string
    {
        if (!openssl_sign($content, $signature, $privatePem, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('夹具签名失败');
        }

        return base64_encode($signature);
    }

    /** @param array<string, mixed> $data */
    private static function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
