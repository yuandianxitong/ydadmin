<?php

declare(strict_types=1);

namespace core\runtime;

/**
 * 统一管理 server 下需可写的 runtime 目录。
 *
 * 本类此前只承载皮肤包子系统（M2.4c.2）所需的 skinPackagesDir()——旧仓库
 * framework-saas/server/core/runtime/RuntimePaths.php 还承载插件包/小程序
 * 构建密钥等目录，当时均不在里程碑范围内故未迁移，避免无测试覆盖的死代码。
 *
 * M4.2c Task 1 补齐插件包子系统所需的两个目录：
 * - pluginPackagesDir()：命名/结构与旧仓库判例一致（旧仓库同名方法，
 *   `runtime/plugin-packages`，供 `plugin:pack` 与 zip 备份复用）。
 * - pluginGraveyardDir()：旧仓库 v2.6.7 引入的软卸载快照目录，旧仓库并未把
 *   它收进 RuntimePaths（内联在 `PluginService::snapshotDatabaseToGraveyard()`
 *   与 `PluginPurgeService::purge()` 里，路径字面量 `runtime/plugin-graveyard/
 *   <pluginId>`），本仓库按 Task 1 接口约定把它收拢进本类统一管理；目录名与
 *   分隔符（连字符 `plugin-graveyard`、按 pluginId 分子目录）与旧仓库路径
 *   字面量保持一致，仅是把散落的字符串拼接收拢成一处，不改变语义。
 */
final class RuntimePaths
{
    /** 官方/本地皮肤 zip 目录（listOfficial 扫描）。 */
    public static function skinPackagesDir(string $rootPath): string
    {
        return rtrim($rootPath, '/\\') . '/runtime/skin-packages';
    }

    /** 插件 zip 备份 / plugin:pack 默认输出目录。旧仓库同名方法判例。 */
    public static function pluginPackagesDir(string $rootPath): string
    {
        return rtrim($rootPath, '/\\') . '/runtime/plugin-packages';
    }

    /**
     * 插件软卸载快照目录（v2.6.7 graveyard 机制）：软卸载时把 `database/` +
     * `plugin.json` 快照到这里，`purge()` 优先用仍保留的代码目录、否则回退
     * 到这里跑清理 SQL；purge 完成后应清理掉。按 pluginId 分子目录，天然
     * 保证清扫不跨插件误删（与 `skinExportDir()` 按 tenantId 分目录同一判例）。
     */
    public static function pluginGraveyardDir(string $rootPath, int $pluginId): string
    {
        return rtrim($rootPath, '/\\') . '/runtime/plugin-graveyard/' . $pluginId;
    }

    /**
     * 租户皮肤导出产物目录（`SkinPackService::export()` 的素材暂存子目录与 zip
     * 成品均落在此目录下；应用自管目录——不像 OS `sys_get_temp_dir()` 那样会被
     * 系统定期清扫，因此必须配合 `SkinPackService::purgeStaleExports()` 的 TTL
     * 清扫使用，见该方法头部说明）。按租户 id 分目录，与 `skin_import` 判例
     * （见 `SkinPackService::importPreview()` 里 `runtime_path('skin_import' .
     * DIRECTORY_SEPARATOR . $tid)`）一致，天然保证清扫只碰自己租户的目录、不
     * 跨租户误删。
     */
    public static function skinExportDir(string $rootPath, int $tenantId): string
    {
        return rtrim($rootPath, '/\\') . '/runtime/skin_export/' . $tenantId;
    }

    /**
     * 官方市场包下载临时落盘目录（marketplace-skin-slice Task 3，
     * `OfficialPackageDownloader::download()` 流式下载的 zip 落这里）。应用
     * 自管目录，同样必须配合该类的 `purgeStaleDownloads()` TTL 清扫使用——
     * 理由同 `skinExportDir()` 头部说明：不像 `sys_get_temp_dir()` 那样会被
     * 系统定期清扫。
     *
     * 不按租户 id 分目录：`marketplace_connections`/`marketplace_public_keys`
     * 均无 `tenant_id` 列（实测见 `MarketplaceRepositoryTest` 头部说明），官方
     * 市场连接是整个部署实例级资源，不是每租户一个，下载/验签动作不发生在
     * 某个租户的请求上下文里；文件名用 16 字节随机 hex（`bin2hex(random_bytes(8))`）
     * 防猜测/防碰撞，天然替代按租户分目录的隔离需求。
     */
    public static function marketplaceDownloadDir(string $rootPath): string
    {
        return rtrim($rootPath, '/\\') . '/runtime/marketplace-downloads';
    }
}
