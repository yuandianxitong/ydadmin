<?php

declare(strict_types=1);

namespace core\mail;

use core\contract\ConfigValueReader;
use core\message\exception\MessageDefiniteFailure;
use core\message\exception\MessageTransientFailure;

/**
 * 用系统配置 smtp_* 发纯文本邮件。不记录口令，失败消息只留 SMTP 状态码或固定短语。
 *
 * 每次 send() 现读配置，改邮件配置不需要 reload。
 */
final class SmtpMailer implements MailerInterface
{
    private const TIMEOUT_SECONDS = 10;

    public function __construct(private ConfigValueReader $config)
    {
    }

    public function send(string $to, string $subject, string $body): void
    {
        $host = trim((string) $this->config->getConfigValue('smtp_host', ''));
        $port = (int) $this->config->getConfigValue('smtp_port', 465);
        $user = trim((string) $this->config->getConfigValue('smtp_user', ''));
        $pass = (string) $this->config->getConfigValue('smtp_pass', '');
        $from = trim((string) $this->config->getConfigValue('smtp_from_address', ''));
        $fromName = trim((string) $this->config->getConfigValue('smtp_from_name', ''));
        $encryption = strtolower(trim((string) $this->config->getConfigValue('smtp_encryption', 'ssl')));

        if ($host === '' || !self::isHost($host) || $from === '' || !self::isMailbox($from) || !self::isMailbox($to)) {
            throw new MessageDefiniteFailure('smtp not configured');
        }
        if (!in_array($encryption, ['ssl', 'tls', 'none'], true) || $port < 1 || $port > 65535) {
            throw new MessageDefiniteFailure('smtp not configured');
        }
        if (self::hasBreak($subject) || self::hasBreak($fromName) || self::hasBreak($to) || self::hasBreak($from) || self::hasBreak($user)) {
            throw new MessageDefiniteFailure('smtp header rejected');
        }

        $remote = ($encryption === 'ssl' ? 'ssl' : 'tcp') . '://' . $host . ':' . $port;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
                'SNI_enabled'      => true,
            ],
        ]);
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($remote, $errno, $errstr, self::TIMEOUT_SECONDS, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            throw new MessageTransientFailure('smtp connect failed');
        }

        try {
            stream_set_timeout($socket, self::TIMEOUT_SECONDS);
            $this->expect($socket, ['220']);
            $this->command($socket, 'EHLO localhost');
            $this->expect($socket, ['250']);

            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS');
                $this->expect($socket, ['220']);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new MessageTransientFailure('smtp starttls failed');
                }
                stream_set_timeout($socket, self::TIMEOUT_SECONDS);
                $this->command($socket, 'EHLO localhost');
                $this->expect($socket, ['250']);
            }

            if ($user !== '') {
                $this->command($socket, 'AUTH LOGIN');
                $this->expect($socket, ['334']);
                $this->command($socket, base64_encode($user));
                $this->expect($socket, ['334']);
                $this->command($socket, base64_encode($pass));
                $this->expect($socket, ['235']);
            }

            $this->command($socket, 'MAIL FROM:<' . $from . '>');
            $this->expect($socket, ['250']);
            $this->command($socket, 'RCPT TO:<' . $to . '>');
            $this->expect($socket, ['250', '251']);
            $this->command($socket, 'DATA');
            $this->expect($socket, ['354']);
            $this->write($socket, $this->message($from, $fromName, $to, $subject, $body));
            $this->expect($socket, ['250']);
            $this->command($socket, 'QUIT');
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket */
    private function message(string $from, string $fromName, string $to, string $subject, string $body): string
    {
        $fromHeader = $fromName === ''
            ? $from
            : self::encodeHeader($fromName) . ' <' . $from . '>';
        $headers = [
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'From: ' . $fromHeader,
            'To: ' . $to,
            'Subject: ' . self::encodeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . self::dotStuff($body) . "\r\n.\r\n";
    }

    /** @param resource $socket */
    private function command($socket, string $command): void
    {
        $this->write($socket, $command . "\r\n");
    }

    /** @param resource $socket */
    private function write($socket, string $payload): void
    {
        $written = @fwrite($socket, $payload);
        if ($written === false || $written !== strlen($payload)) {
            throw new MessageTransientFailure('smtp write failed');
        }
    }

    /**
     * @param resource $socket
     * @param list<string> $ok
     */
    private function expect($socket, array $ok): void
    {
        $code = '';
        do {
            $line = fgets($socket, 515);
            if ($line === false) {
                throw new MessageTransientFailure('smtp connection closed');
            }
            $code = substr($line, 0, 3);
        } while (isset($line[3]) && $line[3] === '-');

        if (in_array($code, $ok, true)) {
            return;
        }
        if (in_array($code, ['421', '450', '451', '452'], true)) {
            throw new MessageTransientFailure('smtp ' . $code);
        }

        throw new MessageDefiniteFailure('smtp ' . $code);
    }

    private static function encodeHeader(string $value): string
    {
        $encoded = mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");

        return str_replace(["\r", "\n"], '', $encoded);
    }

    private static function dotStuff(string $body): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $body);
        $lines = explode("\n", $normalized);
        $stuffed = array_map(static fn (string $line): string => str_starts_with($line, '.') ? '.' . $line : $line, $lines);

        return implode("\r\n", $stuffed);
    }

    private static function isMailbox(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/', $value) === 1;
    }

    private static function isHost(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9.\-]{0,252}$/', $value) === 1;
    }

    private static function hasBreak(string $value): bool
    {
        return str_contains($value, "\r") || str_contains($value, "\n");
    }
}
