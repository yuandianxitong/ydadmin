<?php

declare(strict_types=1);

namespace core\plugin;

/**
 * 在插件属性路由表里按 HTTP 方法与相对路径查找一条定义。
 * 字面路径优先于参数路径；多条参数路径按数组现有顺序，先出现的命中。
 */
final class PluginRouteMatch
{
    /**
     * @param array<int, array{bucket: string, method: string, path: string, controller: string, action: string, apiAuth: bool}> $definitions
     * @return array{bucket: string, method: string, path: string, controller: string, action: string, apiAuth: bool, params: array<string, string>}|null
     */
    public static function find(string $method, string $path, array $definitions): ?array
    {
        $method = strtoupper($method);
        $candidates = [];
        foreach ($definitions as $row) {
            if (strtoupper((string) $row['method']) === $method) {
                $candidates[] = $row;
            }
        }

        foreach ($candidates as $row) {
            if (!self::isParameterized((string) $row['path']) && $row['path'] === $path) {
                $row['params'] = [];

                return $row;
            }
        }

        foreach ($candidates as $row) {
            if (!self::isParameterized((string) $row['path'])) {
                continue;
            }
            $params = self::capture((string) $row['path'], $path);
            if ($params !== null) {
                $row['params'] = $params;

                return $row;
            }
        }

        return null;
    }

    /**
     * 参数正则编译失败时在扫描期失败，避免请求期 preg_match 警告。
     */
    public static function assertValid(string $routePath, string $pluginCode, string $source): void
    {
        if (!self::isParameterized($routePath)) {
            return;
        }
        if (self::compiledPattern($routePath) === null) {
            throw new \RuntimeException("插件 {$pluginCode} 注解路由参数正则无效：{$routePath}（{$source}）");
        }
    }

    private static function isParameterized(string $routePath): bool
    {
        return str_contains($routePath, '{');
    }

    /**
     * 命中时按 `{name}` 出现顺序返回捕获值；未命中返回 null。
     *
     * @return array<string, string>|null
     */
    private static function capture(string $routePath, string $path): ?array
    {
        $names = [];
        $pattern = self::compiledPattern($routePath, $names);
        if ($pattern === null || preg_match($pattern, $path, $matches) !== 1) {
            return null;
        }

        $params = [];
        foreach ($names as $index => $name) {
            $params[$name] = (string) ($matches['p' . $index] ?? '');
        }

        return $params;
    }

    /**
     * @param list<string> $names 按出现顺序收集参数名；组名用 p0、p1…，避免参数名不合法或用户正则自带分组造成错位
     */
    private static function compiledPattern(string $routePath, array &$names = []): ?string
    {
        $names = [];
        $regex = '';
        $offset = 0;
        $length = strlen($routePath);
        while ($offset < $length) {
            $open = strpos($routePath, '{', $offset);
            if ($open === false) {
                $regex .= preg_quote(substr($routePath, $offset), '#');
                break;
            }
            if ($open > $offset) {
                $regex .= preg_quote(substr($routePath, $offset, $open - $offset), '#');
            }
            $close = self::closingBrace($routePath, $open);
            if ($close === null) {
                $regex .= preg_quote(substr($routePath, $open), '#');
                break;
            }
            $inner = substr($routePath, $open + 1, $close - $open - 1);
            $colon = strpos($inner, ':');
            $group = 'p' . count($names);
            if ($colon === false) {
                $names[] = $inner;
                $regex .= '(?P<' . $group . '>[^/]+)';
            } else {
                $names[] = substr($inner, 0, $colon);
                $regex .= '(?P<' . $group . '>' . str_replace('#', '\\#', substr($inner, $colon + 1)) . ')';
            }
            $offset = $close + 1;
        }

        $pattern = '#^' . $regex . '$#';

        return self::isCompilable($pattern) ? $pattern : null;
    }

    private static function closingBrace(string $routePath, int $open): ?int
    {
        $depth = 0;
        $length = strlen($routePath);
        for ($i = $open; $i < $length; $i++) {
            if ($routePath[$i] === '{') {
                $depth++;
            } elseif ($routePath[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private static function isCompilable(string $pattern): bool
    {
        set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
            return true;
        });
        try {
            return preg_match($pattern, '') !== false;
        } finally {
            restore_error_handler();
        }
    }
}
