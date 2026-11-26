<?php

declare(strict_types=1);

namespace app\service\plugin;

use app\repository\plugin\PluginRepository;
use app\repository\plugin\PluginSagaRepository;
use app\service\plugin\adapter\PluginMenuAdapter;
use app\service\plugin\adapter\PluginRouteGeneration;
use core\base\Service;
use core\exception\BusinessException;
use core\plugin\ManifestReader;
use core\plugin\PluginAssetPublisher;
use core\plugin\PluginCategory;
use core\plugin\PluginLifecycleInvoker;
use core\plugin\PluginLoader;
use core\plugin\PluginManifestValidator;
use core\plugin\PluginPackageInstaller;
use core\plugin\PluginPaths;
use core\plugin\PluginRequirements;
use core\plugin\PluginSqlRunner;
use core\plugin\PluginStatus;
use core\plugin\PluginUpgradePackage;
use core\plugin\ReloadSignal;
use core\plugin\saga\PluginSagaRunner;
use core\plugin\saga\PluginSagaState;
use core\plugin\WorkerFingerprintRegistry;
use core\runtime\RuntimePaths;
use DI\Attribute\Inject;

/**
 * 站点级插件安装与启停。
 *
 * 只改插件状态和路由版本号，不调用租户钩子（enable/disable），也不刷新权益缓存。
 * 状态先在请求内写好，再由 saga 让路由上下线；生效失败时 saga 把状态还原。
 */
class PluginService extends Service
{
    #[Inject]
    protected PluginRepository $plugins;

    #[Inject]
    protected PluginSagaRepository $sagas;

    #[Inject]
    protected PluginRouteGeneration $generation;

    #[Inject]
    protected PluginSagaRunner $runner;

    #[Inject]
    protected PluginUpgradePackage $upgradePackage;

    #[Inject]
    protected PluginLoader $loader;

    #[Inject]
    protected PluginLifecycleInvoker $lifecycle;

    #[Inject]
    protected PluginSqlRunner $sqlRunner;

    #[Inject]
    protected PluginMenuAdapter $menus;

    #[Inject]
    protected PluginManifestValidator $validator;

    #[Inject]
    protected PluginPackageInstaller $packages;

    /**
     * @return array{categories: list<array{key: string, label: string, sort: int}>, list: list<array<string, mixed>>}
     */
    public function catalog(): array
    {
        $categories = $this->categoryCatalog();
        $listed = [];
        foreach ($this->plugins->allForCatalog() as $row) {
            $manifest = ManifestReader::decodeColumn($row['manifest'] ?? null);
            $code = (string) $row['code'];
            $listed[$code] = $this->present($code, $manifest, $row, $categories);
        }

        foreach (glob(PluginPaths::root() . '/*/plugin.json') ?: [] as $file) {
            $dir = dirname($file);
            $code = basename($dir);
            if (isset($listed[$code])) {
                continue;
            }
            $manifest = ManifestReader::tryRead($dir);
            if ($manifest === null || (string) ($manifest['code'] ?? '') !== $code) {
                continue;
            }
            $listed[$code] = $this->present($code, $manifest, null, $categories);
        }

        return [
            'categories' => array_values($categories),
            'list' => array_values($listed),
        ];
    }

    /** @return array{id: int, code: string, version: string, status: int} */
    public function install(string $code): array
    {
        $this->assertWorkersRegisteredWhenMasterRuns();

        return $this->installPrepared($code, $this->readManifest($code), [
            'skip_extract' => true,
            'package_path' => '',
            'package_hash' => '',
        ]);
    }

    /** @return array{id: int, code: string, version: string, status: int} */
    public function installFromZip(string $zipPath): array
    {
        $this->assertWorkersRegisteredWhenMasterRuns();
        $manifest = $this->packages->inspect($zipPath);
        $errors = $this->validator->validate($manifest);
        if ($errors !== []) {
            throw new BusinessException(implode('；', $errors), 422);
        }
        $this->menus->assertManifestMenus($manifest);
        $manifest = $this->validator->normalize($manifest);
        $code = (string) $manifest['code'];
        PluginRequirements::assertPlatform(
            $manifest,
            (string) config('version.version'),
            PluginRequirements::enabledCodes($this->plugins)
        );
        if (is_dir(PluginPaths::dir($code))) {
            throw new BusinessException('插件目录已存在，请按 code 安装，或先移走目录：' . PluginPaths::dir($code), 409);
        }
        $staged = $this->stageZip($code, $zipPath);

        return $this->installPrepared($code, $manifest, [
            'skip_extract' => false,
            'package_path' => $staged['path'],
            'package_hash' => $staged['hash'],
        ]);
    }

    /**
     * @param array<string, mixed> $manifest
     * @param array{skip_extract: bool, package_path: string, package_hash: string} $package
     * @return array{id: int, code: string, version: string, status: int}
     */
    private function installPrepared(string $code, array $manifest, array $package): array
    {
        $row = $this->plugins->findByCode($code);
        if ($row !== null && $this->sagas->hasActive((int) $row['id'])) {
            throw new BusinessException('该插件有正在执行的操作，请稍后再试', 409);
        }

        $columns = [
            'name' => (string) ($manifest['name'] ?? $code),
            'version' => (string) ($manifest['version'] ?? ''),
            'kind' => (string) ($manifest['kind'] ?? 'plugin'),
            'description' => (string) ($manifest['description'] ?? ''),
            'author' => (string) ($manifest['author'] ?? ''),
            'icon' => isset($manifest['icon']) ? (string) $manifest['icon'] : null,
            'status' => PluginStatus::INSTALLING,
        ];
        if ($row === null) {
            $row = $this->plugins->create($columns + [
                'code' => $code,
                'manifest' => $manifest,
            ]);
            $previous = PluginStatus::INSTALLING;
        } else {
            $previous = (int) $row['status'];
            if (!in_array($previous, [PluginStatus::INSTALLING, PluginStatus::FAILED, PluginStatus::UNINSTALLED], true)) {
                throw new BusinessException("插件当前状态不可安装（status={$previous}）", 409);
            }
            $stored = $columns;
            $stored['manifest'] = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $this->plugins->update((int) $row['id'], $stored);
            $row = array_merge($row, $columns, ['manifest' => $manifest]);
        }

        $id = (int) $row['id'];
        $saga = $this->sagas->createPending($id, $code, PluginSagaState::OP_INSTALL, [
            'previous_status' => $previous,
            'skip_extract' => $package['skip_extract'],
            'package_path' => $package['package_path'],
            'package_hash' => $package['package_hash'],
            'host_sdk_version' => (string) config('version.host_sdk'),
            'core_version' => (string) config('version.version'),
            'version' => (string) $row['version'],
        ]);

        $exit = $this->runner->handle((int) $saga['id']);
        $after = $this->plugins->find($id) ?? [];
        if ($exit !== 0 || (int) ($after['status'] ?? 0) !== PluginStatus::ENABLED) {
            $error = (string) ($after['last_error'] ?? '');
            throw new BusinessException($error !== '' ? $error : '插件安装失败', 500);
        }

        return [
            'id' => $id,
            'code' => $code,
            'version' => (string) $after['version'],
            'status' => (int) $after['status'],
        ];
    }

    /** @return array{code:string, version:string, status:int} */
    public function upgrade(string $code, string $zipPath): array
    {
        $this->assertWorkersRegisteredWhenMasterRuns();
        $row = $this->requireIdle($code);
        if ((int) $row['status'] !== PluginStatus::ENABLED) {
            throw new BusinessException('仅已启用插件可升级', 409);
        }

        $id = (int) $row['id'];
        $payload = $this->upgradePackage->prepare($code, (string) $row['version'], $zipPath);
        try {
            $saga = $this->runInTransaction(function () use ($id, $code, $payload): array {
                $affected = $this->plugins->updateWhere(
                    ['id' => $id, 'status' => PluginStatus::ENABLED, 'version' => $payload['from_version']],
                    ['status' => PluginStatus::UPGRADING],
                );
                if ($affected !== 1 || $this->sagas->hasActive($id)) {
                    throw new BusinessException('插件状态已变化，请重试', 409);
                }
                $this->generation->bump();
                return $this->sagas->createPending($id, $code, PluginSagaState::OP_UPGRADE, $payload);
            });
        } catch (\Throwable $e) {
            @unlink($payload['staged_zip']);
            throw $e;
        }

        $exit = $this->runner->handle((int) $saga['id']);
        $after = $this->plugins->find($id) ?? [];
        if ($exit !== 0 || ($after['version'] ?? null) !== $payload['to_version']) {
            throw new BusinessException((string) (($after['last_error'] ?? '') ?: '插件升级失败'), 500);
        }

        return ['code' => $code, 'version' => $payload['to_version'], 'status' => (int) $after['status']];
    }

    /** @return array{id: int, code: string, status: int} */
    public function disable(string $code): array
    {
        $this->assertWorkersRegisteredWhenMasterRuns();

        $row = $this->requireIdle($code);
        $status = (int) $row['status'];
        if ($status !== PluginStatus::ENABLED) {
            throw new BusinessException("插件当前状态不可禁用（status={$status}）", 409);
        }

        $dependents = $this->plugins->findReverseDependents($code);
        if ($dependents !== []) {
            throw new BusinessException('插件被依赖，无法禁用：' . $code . ' ← ' . implode(',', $dependents), 400);
        }

        return $this->transition($row, PluginStatus::ENABLED, PluginStatus::DISABLED, PluginSagaState::OP_DISABLE);
    }

    /** @return array{id: int, code: string, status: int} */
    public function enable(string $code): array
    {
        $this->assertWorkersRegisteredWhenMasterRuns();

        $row = $this->requireIdle($code);
        $status = (int) $row['status'];
        if ($status !== PluginStatus::DISABLED) {
            throw new BusinessException("插件当前状态不可启用（status={$status}）", 409);
        }
        if (ManifestReader::tryRead(PluginPaths::dir($code)) === null) {
            throw new BusinessException("插件代码目录缺失（plugins/{$code}），请重新安装", 409);
        }

        PluginRequirements::assertPlatform(
            ManifestReader::decodeColumn($row['manifest'] ?? null),
            (string) config('version.version'),
            PluginRequirements::enabledCodes($this->plugins)
        );

        return $this->transition($row, PluginStatus::DISABLED, PluginStatus::ENABLED, PluginSagaState::OP_ENABLE);
    }

    /** @return array{code:string, status:string} */
    public function uninstall(string $code, bool $purge = false): array
    {
        $plugin = $this->requireIdle($code);
        $status = (int) $plugin['status'];
        if ($purge && $status === PluginStatus::ENABLED) {
            throw new BusinessException(
                "清理数据前请先停用：php webman plugin:disable {$code}，然后再执行 php webman plugin:uninstall {$code} --purge",
                409
            );
        }
        if (!in_array($status, [PluginStatus::DISABLED, PluginStatus::FAILED, PluginStatus::ENABLED], true)
            || ($purge && $status !== PluginStatus::DISABLED)) {
            throw new BusinessException('插件当前状态不可卸载，清理数据前请先停用', 409);
        }
        if ($status === PluginStatus::ENABLED) {
            $this->disable($code);
        }

        $id = (int) $plugin['id'];
        $dir = PluginPaths::dir($code);
        $manifest = ManifestReader::decodeColumn($plugin['manifest'] ?? null);
        $this->loader->registerPsr4($manifest, $dir);
        $this->lifecycle->invoke($manifest, 'uninstall');
        if ($purge) {
            $this->sqlRunner->uninstallData($dir, $id, $code, (string) $plugin['version']);
        }
        $this->runInTransaction(function () use ($id, $code): void {
            $this->menus->remove($code);
            $this->plugins->update($id, [
                'status' => PluginStatus::UNINSTALLED,
                'last_error' => '',
            ]);
        });

        return ['code' => $code, 'status' => 'uninstalled'];
    }

    /**
     * 有 master 在跑、却没有任何 worker 登记过插件指纹时，reload 永远等不到收敛：
     * 信号会真发出去，随后两轮各等 60 秒并把插件置为失败。这里在任何写入之前直接拒绝。
     * master 没在跑时（masterPid 抛错）不拦，照常往下走。
     */
    private function assertWorkersRegisteredWhenMasterRuns(): void
    {
        try {
            ReloadSignal::masterPid();
        } catch (BusinessException) {
            return;
        }

        if (WorkerFingerprintRegistry::liveWorkers() === []) {
            throw new BusinessException('未登记插件指纹的 worker，不能对运行中的 master 执行插件操作', 409);
        }
    }

    /**
     * 状态与路由版本号在同一事务里写好，再交给 saga 让路由生效。
     *
     * @param array<string, mixed> $row
     * @return array{id: int, code: string, status: int}
     */
    private function transition(array $row, int $from, int $to, string $operation): array
    {
        $id = (int) $row['id'];
        $code = (string) $row['code'];

        $saga = $this->runInTransaction(function () use ($id, $code, $from, $to, $operation): array {
            $affected = $this->plugins->updateWhere(['id' => $id, 'status' => $from], ['status' => $to]);
            if ($affected !== 1 || $this->sagas->hasActive($id)) {
                throw new BusinessException('插件状态已变化，请重试', 409);
            }
            if ($this->generation->shouldBump($from, $to, false)) {
                $this->generation->bump();
            }

            return $this->sagas->createPending($id, $code, $operation, ['previous_status' => $from]);
        });
        $exit = $this->runner->handle((int) $saga['id']);
        $after = $this->plugins->find($id) ?? [];
        if ($exit !== 0) {
            $error = (string) ($after['last_error'] ?? '');
            throw new BusinessException($error !== '' ? $error : '插件操作失败', 500);
        }

        $this->plugins->update($id, ['last_error' => '']);
        $this->menus->setStatus($code, $to === PluginStatus::ENABLED ? 1 : 0);

        return ['id' => $id, 'code' => $code, 'status' => (int) ($after['status'] ?? $to)];
    }

    /** @return array<string, mixed> */
    private function requireIdle(string $code): array
    {
        $row = $this->plugins->findByCode($code);
        if ($row === null) {
            throw new BusinessException('插件不存在', 404);
        }
        if ($this->sagas->hasActive((int) $row['id'])) {
            throw new BusinessException('该插件有正在执行的操作，请稍后再试', 409);
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function readManifest(string $code): array
    {
        $manifest = ManifestReader::tryRead(PluginPaths::dir($code));
        if ($manifest === null || (string) ($manifest['code'] ?? '') !== $code) {
            throw new BusinessException('插件目录不存在：' . PluginPaths::dir($code), 409);
        }

        $errors = $this->validator->validate($manifest);
        if ($errors !== []) {
            throw new BusinessException(implode('；', $errors), 422);
        }
        $this->menus->assertManifestMenus($manifest);
        PluginAssetPublisher::assertDistIfNeeded(PluginPaths::dir($code));

        return $this->validator->normalize($manifest);
    }

    /**
     * @return list<array{key: string, label: string, sort: int}>
     */
    private function categoryCatalog(): array
    {
        $categories = [];
        foreach ((array) config('plugin_host.categories', []) as $key => $meta) {
            if (!is_string($key) || !is_array($meta)) {
                continue;
            }
            $categories[] = [
                'key' => $key,
                'label' => (string) ($meta['label'] ?? $key),
                'sort' => (int) ($meta['sort'] ?? 0),
            ];
        }
        usort($categories, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return $categories;
    }

    /**
     * @param array<string, mixed> $manifest
     * @param array<string, mixed>|null $row
     * @param list<array{key: string, label: string, sort: int}> $categories
     * @return array<string, mixed>
     */
    private function present(string $code, array $manifest, ?array $row, array $categories): array
    {
        $table = [];
        foreach ($categories as $category) {
            $table[$category['key']] = [
                'label' => $category['label'],
                'sort' => $category['sort'],
            ];
        }

        return [
            'code' => $code,
            'name' => (string) ($row['name'] ?? $manifest['name'] ?? $code),
            'version' => (string) ($row['version'] ?? $manifest['version'] ?? ''),
            'description' => (string) ($row['description'] ?? $manifest['description'] ?? ''),
            'icon' => (string) ($row['icon'] ?? $manifest['icon'] ?? ''),
            'kind' => (string) ($row['kind'] ?? $manifest['kind'] ?? 'plugin'),
            'category' => PluginCategory::normalize(isset($manifest['category']) ? (string) $manifest['category'] : null, $table),
            'status' => $row === null ? 0 : (int) $row['status'],
            'last_error' => (string) ($row['last_error'] ?? ''),
        ];
    }

    /** @return array{path: string, hash: string} */
    private function stageZip(string $code, string $zipPath): array
    {
        $folder = RuntimePaths::pluginPackagesDir(base_path()) . '/staging';
        if (!is_dir($folder) && !@mkdir($folder, 0755, true) && !is_dir($folder)) {
            throw new BusinessException('无法创建插件包暂存目录', 500);
        }
        $staged = $folder . '/' . $code . '-' . bin2hex(random_bytes(12)) . '.zip';
        if (!@copy($zipPath, $staged) || ($hash = hash_file('sha256', $staged)) === false) {
            @unlink($staged);
            throw new BusinessException('无法暂存并计算插件包的校验值', 500);
        }

        return ['path' => $staged, 'hash' => $hash];
    }
}
