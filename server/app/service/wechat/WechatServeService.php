<?php

declare(strict_types=1);

namespace app\service\wechat;

use app\service\wechat\dto\ServeAck;
use core\wechat\OfficialServerConfig;
use core\wechat\WechatConfigResolver;
use core\wechat\WxMsgCrypt;
use SimpleXMLElement;
use support\Log;

/**
 * 公众号服务器 URL 接入与消息回调（spec §5.1）。
 * 容器单例，不保存请求态；验签失败回空 body，业务异常回 success，不调微信 HTTP、不投队列。
 */
final class WechatServeService
{
    private const PLAIN = 'text/plain; charset=utf-8';

    private const XML = 'application/xml; charset=utf-8';

    public function __construct(
        private readonly WechatConfigResolver $wechatConfigResolver,
        private readonly AutoReplyService $autoReplyService,
    ) {
    }

    public function handleGet(string $signature, string $timestamp, string $nonce, string $echostr): ServeAck
    {
        $config = $this->officialServerOrNull();
        if ($config === null || !$this->urlSignatureOk($config->token, $timestamp, $nonce, $signature)) {
            return $this->emptyAck();
        }

        return new ServeAck(200, self::PLAIN, $echostr);
    }

    public function handlePost(
        string $signature,
        string $timestamp,
        string $nonce,
        string $rawBody,
        string $encryptTypeQuery,
        string $msgSignature,
    ): ServeAck {
        $config = $this->officialServerOrNull();
        if ($config === null) {
            return $this->emptyAck();
        }

        $outer = $this->parseXml($rawBody);
        if ($outer === null) {
            return $this->emptyAck();
        }

        $cipher = $encryptTypeQuery === 'aes' || $this->hasChild($outer, 'Encrypt');
        if (($config->encryptType === 1 && $cipher) || ($config->encryptType === 3 && !$cipher)) {
            return $this->emptyAck();
        }

        if ($cipher) {
            $xml = $this->openCipher($config, $outer, $timestamp, $nonce, $msgSignature);
        } elseif ($this->urlSignatureOk($config->token, $timestamp, $nonce, $signature)) {
            $xml = $outer;
        } else {
            $xml = null;
        }
        if ($xml === null) {
            return $this->emptyAck();
        }

        return $this->reply($config, $xml, $cipher, $timestamp, $nonce);
    }

    private function officialServerOrNull(): ?OfficialServerConfig
    {
        try {
            return $this->wechatConfigResolver->officialServer();
        } catch (\Throwable) {
            return null;
        }
    }

    private function urlSignatureOk(string $token, string $timestamp, string $nonce, string $signature): bool
    {
        return WxMsgCrypt::equals(WxMsgCrypt::urlSignature($token, $timestamp, $nonce), $signature);
    }

    private function openCipher(
        OfficialServerConfig $config,
        SimpleXMLElement $outer,
        string $timestamp,
        string $nonce,
        string $msgSignature,
    ): ?SimpleXMLElement {
        $encrypt = $this->xmlVal($outer, 'Encrypt');
        if ($encrypt === '' || !WxMsgCrypt::equals(WxMsgCrypt::msgSignature($config->token, $timestamp, $nonce, $encrypt), $msgSignature)) {
            return null;
        }
        $plain = WxMsgCrypt::decrypt($config->aesKey, $encrypt, $config->appId);
        if ($plain === null) {
            return null;
        }

        return $this->parseXml($plain);
    }

    private function reply(
        OfficialServerConfig $config,
        SimpleXMLElement $xml,
        bool $cipher,
        string $timestamp,
        string $nonce,
    ): ServeAck {
        $msgType = $this->xmlVal($xml, 'MsgType');
        $event = $this->xmlVal($xml, 'Event');
        $replyDecided = false;
        try {
            $content = $this->decideReply($msgType, $event, $xml);
            if ($content === null || $content === '') {
                return $this->successAck();
            }
            $replyDecided = true;
            $plain = WxMsgCrypt::textReplyXml(
                $this->xmlVal($xml, 'FromUserName'),
                $this->xmlVal($xml, 'ToUserName'),
                $content,
                time(),
            );
            if (!$cipher) {
                return new ServeAck(200, self::XML, $plain);
            }
            $replyNonce = $nonce !== '' ? $nonce : bin2hex(random_bytes(4));
            $encrypted = WxMsgCrypt::encrypt($config->aesKey, $plain, $config->appId);
            if ($encrypted === null) {
                throw new \RuntimeException('encrypt');
            }

            return new ServeAck(200, self::XML, WxMsgCrypt::encryptedEnvelope($config->token, $timestamp, $replyNonce, $encrypted));
        } catch (\Throwable) {
            Log::warning('微信公众号消息处理失败', [
                'msg_type' => $msgType,
                'event' => $event,
                'reply_decided' => $replyDecided,
            ]);

            return $this->successAck();
        }
    }

    private function decideReply(string $msgType, string $event, SimpleXMLElement $xml): ?string
    {
        if (strcasecmp($event, 'subscribe') === 0) {
            return $this->autoReplyService->subscribeReply();
        }
        if (strcasecmp($msgType, 'text') === 0) {
            return $this->autoReplyService->matchKeyword($this->xmlVal($xml, 'Content'));
        }
        if (strcasecmp($event, 'CLICK') === 0) {
            return $this->autoReplyService->matchKeyword($this->xmlVal($xml, 'EventKey'));
        }

        return null;
    }

    private function parseXml(string $raw): ?SimpleXMLElement
    {
        if ($raw === '') {
            return null;
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($raw);

            return $xml instanceof SimpleXMLElement ? $xml : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function xmlVal(SimpleXMLElement $xml, string $name): string
    {
        foreach ($xml->children() as $child) {
            if (strcasecmp($child->getName(), $name) === 0) {
                return (string) $child;
            }
        }

        return '';
    }

    private function hasChild(SimpleXMLElement $xml, string $name): bool
    {
        foreach ($xml->children() as $child) {
            if (strcasecmp($child->getName(), $name) === 0) {
                return true;
            }
        }

        return false;
    }

    private function emptyAck(): ServeAck
    {
        return new ServeAck(200, self::PLAIN, '');
    }

    private function successAck(): ServeAck
    {
        return new ServeAck(200, self::PLAIN, 'success');
    }
}
