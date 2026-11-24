<?php

declare(strict_types=1);

namespace core\plugin\contracts;

interface RouteBuckets
{
    /** @return list<string> 分发器接管的 URL 第一段 */
    public function prefixes(): array;

    /** @return list<string> 某个 URL 第一段下要查的分组 */
    public function bucketsForPrefix(string $prefix): array;

    /** @return array<string, string> manifest.routes 的键 => 插件内控制器目录，顺序即加载顺序 */
    public function controllerDirs(): array;

    /** @return array<string, string> 匿名分组 => 共用同一控制器目录的登录分组 */
    public function authPairs(): array;

    /** manifest.routes 一个都没声明时的错误消息 */
    public function missingRoutesError(): string;

    /**
     * @param array<string, mixed> $manifest
     * @return list<string|object>
     */
    public function chain(string $bucket, string $code, array $manifest): array;
}
