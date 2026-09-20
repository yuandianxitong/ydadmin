<?php

declare(strict_types=1);

namespace tests\Unit\Wechat;

use core\wechat\WxMsgCrypt;
use tests\TestCase;

final class WxMsgCryptTest extends TestCase
{
    private const AES_KEY = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFG';

    public function test_url_signature_matches_manual_calculation_and_rejects_wrong_signature(): void
    {
        $parts = ['token-value', '1710000000', 'nonce-value'];
        sort($parts, SORT_STRING);
        $expected = sha1(implode('', $parts));

        $this->assertSame($expected, WxMsgCrypt::urlSignature('token-value', '1710000000', 'nonce-value'));
        $this->assertTrue(WxMsgCrypt::equals($expected, $expected));
        $this->assertFalse(WxMsgCrypt::equals($expected, 'wrong-signature'));
        $this->assertFalse(WxMsgCrypt::equals('', ''));
    }

    public function test_encrypt_and_decrypt_round_trip_and_rejects_wrong_app_id(): void
    {
        $xml = '<xml><Content><![CDATA[' . bin2hex(random_bytes(16)) . ']]></Content></xml>';
        $cipher = WxMsgCrypt::encrypt(self::AES_KEY, $xml, 'wx-app-id');

        $this->assertNotNull($cipher);
        $this->assertSame($xml, WxMsgCrypt::decrypt(self::AES_KEY, $cipher, 'wx-app-id'));
        $this->assertNull(WxMsgCrypt::decrypt(self::AES_KEY, $cipher, 'another-app-id'));
    }

    public function test_decrypt_rejects_bad_base64_and_short_aes_key(): void
    {
        $this->assertNull(WxMsgCrypt::decrypt(self::AES_KEY, 'not-valid-base64!', 'wx-app-id'));
        $this->assertNull(WxMsgCrypt::decrypt('too-short', base64_encode('cipher'), 'wx-app-id'));
    }

    public function test_text_reply_xml_preserves_cdata_terminator_in_content(): void
    {
        $content = 'before ]]> after';
        $xml = WxMsgCrypt::textReplyXml('to-user', 'from-user', $content, 1710000000);
        $parsed = simplexml_load_string($xml);

        $this->assertNotFalse($parsed);
        $this->assertStringContainsString('<![CDATA[', $xml);
        $this->assertSame($content, (string) $parsed->Content);
    }

    public function test_encrypted_envelope_contains_all_required_nodes(): void
    {
        $xml = WxMsgCrypt::encryptedEnvelope('token', '1710000000', 'nonce', 'cipher');
        $parsed = simplexml_load_string($xml);

        $this->assertNotFalse($parsed);
        $this->assertSame('cipher', (string) $parsed->Encrypt);
        $this->assertSame(WxMsgCrypt::msgSignature('token', '1710000000', 'nonce', 'cipher'), (string) $parsed->MsgSignature);
        $this->assertSame('1710000000', (string) $parsed->TimeStamp);
        $this->assertSame('nonce', (string) $parsed->Nonce);
    }
}
