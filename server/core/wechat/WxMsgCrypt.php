<?php

declare(strict_types=1);

namespace core\wechat;

final class WxMsgCrypt
{
    private const BLOCK = 32;

    public static function urlSignature(string $token, string $timestamp, string $nonce): string
    {
        $arr = [$token, $timestamp, $nonce];
        sort($arr, SORT_STRING);

        return sha1(implode('', $arr));
    }

    public static function msgSignature(string $token, string $timestamp, string $nonce, string $encrypt): string
    {
        $arr = [$token, $timestamp, $nonce, $encrypt];
        sort($arr, SORT_STRING);

        return sha1(implode('', $arr));
    }

    public static function equals(string $expected, string $actual): bool
    {
        return $expected !== '' && hash_equals($expected, $actual);
    }

    public static function decrypt(string $aesKey43, string $cipherB64, string $appId): ?string
    {
        $key = self::aesKey($aesKey43);
        $raw = base64_decode($cipherB64, true);
        if ($key === null || $raw === false || $raw === '') {
            return null;
        }
        $plain = openssl_decrypt($raw, 'AES-256-CBC', $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, substr($key, 0, 16));
        if (!is_string($plain) || $plain === '') {
            return null;
        }
        $plain = self::unpad($plain);
        if (strlen($plain) < 20) {
            return null;
        }
        $xmlLen = unpack('N', substr($plain, 16, 4));
        if (!is_array($xmlLen)) {
            return null;
        }
        $len = (int) $xmlLen[1];
        $xml = substr($plain, 20, $len);
        $tail = substr($plain, 20 + $len);
        if ($xml === '' || $tail !== $appId) {
            return null;
        }

        return $xml;
    }

    public static function encrypt(string $aesKey43, string $xml, string $appId): ?string
    {
        $key = self::aesKey($aesKey43);
        if ($key === null) {
            return null;
        }
        $block = random_bytes(16) . pack('N', strlen($xml)) . $xml . $appId;
        $padded = self::pad($block);
        $raw = openssl_encrypt($padded, 'AES-256-CBC', $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, substr($key, 0, 16));

        return is_string($raw) && $raw !== '' ? base64_encode($raw) : null;
    }

    public static function textReplyXml(string $toUser, string $fromUser, string $content, int $createTime): string
    {
        return '<xml>'
            . '<ToUserName><![CDATA[' . self::cdata($toUser) . ']]></ToUserName>'
            . '<FromUserName><![CDATA[' . self::cdata($fromUser) . ']]></FromUserName>'
            . '<CreateTime>' . $createTime . '</CreateTime>'
            . '<MsgType><![CDATA[text]]></MsgType>'
            . '<Content><![CDATA[' . self::cdata($content) . ']]></Content>'
            . '</xml>';
    }

    public static function encryptedEnvelope(string $token, string $timestamp, string $nonce, string $encrypt): string
    {
        $sig = self::msgSignature($token, $timestamp, $nonce, $encrypt);

        return '<xml>'
            . '<Encrypt><![CDATA[' . self::cdata($encrypt) . ']]></Encrypt>'
            . '<MsgSignature><![CDATA[' . $sig . ']]></MsgSignature>'
            . '<TimeStamp>' . $timestamp . '</TimeStamp>'
            . '<Nonce><![CDATA[' . self::cdata($nonce) . ']]></Nonce>'
            . '</xml>';
    }

    private static function cdata(string $value): string
    {
        return str_replace(']]>', ']]]]><![CDATA[>', $value);
    }

    private static function aesKey(string $aesKey43): ?string
    {
        if (strlen($aesKey43) !== 43) {
            return null;
        }
        $key = base64_decode($aesKey43 . '=', true);

        return is_string($key) && strlen($key) === 32 ? $key : null;
    }

    private static function pad(string $text): string
    {
        $amount = self::BLOCK - (strlen($text) % self::BLOCK);

        return $text . str_repeat(chr($amount), $amount);
    }

    private static function unpad(string $text): string
    {
        $pad = ord(substr($text, -1));
        if ($pad < 1 || $pad > self::BLOCK) {
            return '';
        }

        return substr($text, 0, -$pad);
    }
}
