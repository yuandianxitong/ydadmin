<?php

declare(strict_types=1);

namespace core\plugin\contracts;

interface PluginStateListener
{
    /**
     * 启用或升级完成时与 status 同一条 UPDATE 写入的宿主列。
     *
     * 返回的键集合不能随 manifest 变化：MarkUpgradedStep 用 enabledColumns($code, []) 的键
     * 决定补偿时还原哪些列。不能包含内核自己写的列（status、last_error、installed_at、
     * depends、version、name、description、manifest、read_safe_routes、package_path、package_hash）。
     *
     * @return array<string, mixed>
     */
    public function enabledColumns(string $code, array $manifest): array;

    /** 插件状态变化已提交 */
    public function statusChanged(): void;
}
