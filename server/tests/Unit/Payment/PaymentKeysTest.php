<?php

declare(strict_types=1);

namespace tests\Unit\Payment;

use tests\Support\Payment\PaymentKeys;
use tests\TestCase;

/** 夹具自测：后续支付任务的离线测试都建立在这几个方法上，先钉住它们的形状。 */
final class PaymentKeysTest extends TestCase
{
    public function test_rsa_pair_signs_and_verifies(): void
    {
        $pair = PaymentKeys::rsaPair();

        $this->assertStringContainsString('-----BEGIN PRIVATE KEY-----', $pair['private']);
        $this->assertStringContainsString('-----BEGIN PUBLIC KEY-----', $pair['public']);
        $this->assertTrue(openssl_sign('hello', $signature, $pair['private'], OPENSSL_ALGO_SHA256));
        $this->assertSame(1, openssl_verify('hello', $signature, $pair['public'], OPENSSL_ALGO_SHA256));
    }

    public function test_self_signed_cert_carries_real_serial_and_public_key(): void
    {
        $pair = PaymentKeys::rsaPair();
        $cert = PaymentKeys::selfSignedCert($pair['private'], 0x5A3C);

        $this->assertSame('5A3C', $cert['serial']);
        $this->assertStringContainsString('-----BEGIN CERTIFICATE-----', $cert['cert']);
        $this->assertTrue(openssl_sign('hello', $signature, $pair['private'], OPENSSL_ALGO_SHA256));
        $this->assertSame(1, openssl_verify('hello', $signature, $cert['cert'], OPENSSL_ALGO_SHA256));
    }

    public function test_temp_dir_is_created_and_removed_recursively(): void
    {
        $dir = PaymentKeys::tempDir();
        mkdir($dir . '/nested');
        file_put_contents($dir . '/nested/a.pem', 'x');

        PaymentKeys::removeDir($dir);

        $this->assertDirectoryDoesNotExist($dir);
    }
}
