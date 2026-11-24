<?php

declare(strict_types=1);

namespace app\service\plugin;

use app\repository\plugin\PluginRepository;
use app\repository\plugin\PluginSagaRepository;
use app\service\plugin\adapter\PluginRouteGeneration;
use core\base\Service;
use core\exception\BusinessException;
use core\plugin\ManifestReader;
use core\plugin\PluginPaths;
use core\plugin\PluginStatus;
use core\plugin\saga\PluginSagaRunner;
use core\plugin\saga\PluginSagaState;
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

    /** @return array{id: int, code: string, version: string, status: int} */
    public function install(string $code): array
    {
        $row = $this->plugins->findByCode($code);
        if ($row !== null && $this->sagas->hasActive((int) $row['id'])) {
            throw new BusinessException('该插件有正在执行的操作，请稍后再试', 409);
        }

        if ($row === null) {
            $manifest = $this->readManifest($code);
            $row = $this->plugins->create([
                'code' => $code,
                'name' => (string) ($manifest['name'] ?? $code),
                'version' => (string) ($manifest['version'] ?? ''),
                'kind' => (string) ($manifest['kind'] ?? 'plugin'),
                'manifest' => $manifest,
                'status' => PluginStatus::INSTALLING,
            ]);
            $previous = PluginStatus::INSTALLING;
        } else {
            $previous = (int) $row['status'];
            if (!in_array($previous, [PluginStatus::INSTALLING, PluginStatus::FAILED, PluginStatus::UNINSTALLED], true)) {
                throw new BusinessException("插件当前状态不可安装（status={$previous}）", 409);
            }
            $this->readManifest($code);
            $this->plugins->update((int) $row['id'], ['status' => PluginStatus::INSTALLING]);
        }

        $id = (int) $row['id'];
        $saga = $this->sagas->createPending($id, $code, PluginSagaState::OP_INSTALL, [
            'previous_status' => $previous,
            'skip_extract' => true,
            'package_path' => '',
            'package_hash' => '',
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

    /** @return array{id: int, code: string, status: int} */
    public function disable(string $code): array
    {
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
        $row = $this->requireIdle($code);
        $status = (int) $row['status'];
        if ($status !== PluginStatus::DISABLED) {
            throw new BusinessException("插件当前状态不可启用（status={$status}）", 409);
        }
        if (ManifestReader::tryRead(PluginPaths::dir($code)) === null) {
            throw new BusinessException("插件代码目录缺失（plugins/{$code}），请重新安装", 409);
        }

        return $this->transition($row, PluginStatus::DISABLED, PluginStatus::ENABLED, PluginSagaState::OP_ENABLE);
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

        $this->runInTransaction(function () use ($id, $from, $to): void {
            $this->plugins->update($id, ['status' => $to]);
            if ($this->generation->shouldBump($from, $to, false)) {
                $this->generation->bump();
            }
        });

        $saga = $this->sagas->createPending($id, $code, $operation, ['previous_status' => $from]);
        $exit = $this->runner->handle((int) $saga['id']);
        $after = $this->plugins->find($id) ?? [];
        if ($exit !== 0) {
            $error = (string) ($after['last_error'] ?? '');
            throw new BusinessException($error !== '' ? $error : '插件操作失败', 500);
        }

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

        return $manifest;
    }
}
