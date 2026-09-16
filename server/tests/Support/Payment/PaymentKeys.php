<?php

declare(strict_types=1);

namespace tests\Support\Payment;

/**
 * 支付测试用的密钥与证书：全部运行时生成，不提交任何 PEM 文件（M5b 计划 Global Constraints「测试密钥」）。
 * 生成 2048 位 RSA 较慢（数十毫秒），测试类应在 setUpBeforeClass() 里生成一次复用。
 */
final class PaymentKeys
{
    /** @return array{private: string, public: string} PKCS#8 私钥 PEM 与 SubjectPublicKeyInfo 公钥 PEM */
    public static function rsaPair(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($key === false) {
            throw new \RuntimeException('openssl_pkey_new 失败：' . (string) openssl_error_string());
        }
        if (!openssl_pkey_export($key, $private)) {
            throw new \RuntimeException('openssl_pkey_export 失败');
        }
        $details = openssl_pkey_get_details($key);
        if ($details === false) {
            throw new \RuntimeException('openssl_pkey_get_details 失败');
        }

        return ['private' => $private, 'public' => $details['key']];
    }

    /**
     * 用给定私钥自签一张证书（充当微信支付平台证书）。
     *
     * @return array{cert: string, serial: string} serial 为证书里真实的序列号，大写十六进制
     */
    public static function selfSignedCert(string $privatePem, int $serial): array
    {
        $key = openssl_pkey_get_private($privatePem);
        if ($key === false) {
            throw new \RuntimeException('夹具私钥无法解析');
        }
        $csr = openssl_csr_new(['commonName' => 'ydadmin-payment-test'], $key, ['digest_alg' => 'sha256']);
        if ($csr === false) {
            throw new \RuntimeException('openssl_csr_new 失败：' . (string) openssl_error_string());
        }
        $x509 = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256'], $serial);
        if ($x509 === false || !openssl_x509_export($x509, $cert)) {
            throw new \RuntimeException('openssl_csr_sign 失败：' . (string) openssl_error_string());
        }
        $parsed = openssl_x509_parse($x509);
        if ($parsed === false) {
            throw new \RuntimeException('openssl_x509_parse 失败');
        }

        return ['cert' => $cert, 'serial' => strtoupper((string) $parsed['serialNumberHex'])];
    }

    public static function tempDir(): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ydadmin-pay-' . bin2hex(random_bytes(6));
        if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException("无法创建临时目录 {$dir}");
        }

        return $dir;
    }

    public static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
