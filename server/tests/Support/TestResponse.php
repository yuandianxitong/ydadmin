<?php

declare(strict_types=1);

namespace tests\Support;

use PHPUnit\Framework\Assert;
use Workerman\Protocols\Http\Response;

final class TestResponse
{
    private Response $response;

    public function __construct(mixed $response)
    {
        Assert::assertInstanceOf(Response::class, $response, '请求没有产生 HTTP 响应');
        $this->response = $response;
    }

    public function status(): int
    {
        return $this->response->getStatusCode();
    }

    public function header(string $name): ?string
    {
        foreach ($this->response->getHeaders() as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return is_array($value) ? implode(', ', $value) : (string) $value;
            }
        }

        return null;
    }

    public function body(): string
    {
        return (string) $this->response->rawBody();
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        $decoded = json_decode($this->body(), true);
        Assert::assertIsArray($decoded, '响应不是 JSON：' . $this->body());

        return $decoded;
    }

    public function code(): int
    {
        return (int) ($this->json()['code'] ?? 0);
    }

    public function message(): string
    {
        return (string) ($this->json()['message'] ?? '');
    }

    public function data(): mixed
    {
        return $this->json()['data'] ?? null;
    }

    public function assertCode(int $code): self
    {
        $json = $this->json();
        Assert::assertSame(['code', 'message', 'data', 'timestamp'], array_keys($json), '响应信封字段不符：' . $this->body());
        Assert::assertSame($code, $json['code'], 'body.code 不符：' . $this->body());

        return $this;
    }

    public function assertOk(): self
    {
        return $this->assertCode(200);
    }
}
