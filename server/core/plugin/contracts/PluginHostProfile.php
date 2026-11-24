<?php

declare(strict_types=1);

namespace core\plugin\contracts;

interface PluginHostProfile
{
    public function strictMigrationHash(): bool;

    /** @return array<string, array{label: string, sort: int}> */
    public function categories(): array;

    public function reloadTimeoutSeconds(): int;

    public function reloadPollIntervalMs(): int;

    /**
     * 可发布、可做 hostSdk 把关的前端宿主。键的顺序就是 hosts() 的顺序。
     *
     * 每个值含：
     * - vendors: list<string>
     * - prefix: string          例如 @yd/host
     * - modules: list<string>
     * - sdkSource: string       相对仓库根，例如 tenant/build/host-sdk.ts
     *
     * @return array<string, array{vendors: list<string>, prefix: string, modules: list<string>, sdkSource: string}>
     */
    public function publishableFrontends(): array;

    /**
     * 清单与 zip 里 diy renderer、源码目录允许的端，含 uniapp。
     *
     * @return list<string>
     */
    public function sourceEnds(): array;
}
