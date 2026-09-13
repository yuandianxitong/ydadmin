<?php

declare(strict_types=1);

namespace core\apidoc;

/** 路由 + 反射推出的一个端点的全部已知信息。纯值对象，不做任何 I/O。 */
final class EndpointDescriptor
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly string $controller,
        public readonly string $action,
        public readonly ?string $permission,
        public readonly bool $permissionSkipped,
        public readonly string $tag,
    ) {
    }

    /** 'DictionaryController::show'：控制器短名（去掉命名空间）+ 动作名。 */
    public function operationId(): string
    {
        $parts = explode('\\', $this->controller);

        return end($parts) . '::' . $this->action;
    }

    /**
     * 从 path 里的 `{name}` / `{name:regex}` 解析路径参数。regex 恰好是 `\d+` 时判 integer，
     * 没有 regex 或 regex 不是 `\d+` 时一律判 string——不去猜测其它正则代表什么类型。
     *
     * @return list<array{name: string, type: string}>
     */
    public function pathParameters(): array
    {
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]*))?\}/', $this->path, $matches, PREG_SET_ORDER);

        return array_map(
            static fn (array $match): array => [
                'name' => $match[1],
                'type' => ($match[2] ?? '') === '\d+' ? 'integer' : 'string',
            ],
            $matches
        );
    }

    /** POST/PUT/PATCH 走 requestBody；GET/DELETE 走 query（spec §6「query 还是 body」）。 */
    public function expectsBody(): bool
    {
        return in_array($this->method, ['POST', 'PUT', 'PATCH'], true);
    }

    /** 与收敛后的控制器包装方法同名：`{$action}Rules`。 */
    public function rulesMethod(): string
    {
        return "{$this->action}Rules";
    }
}
