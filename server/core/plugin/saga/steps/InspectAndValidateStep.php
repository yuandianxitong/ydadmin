<?php

declare(strict_types=1);

namespace core\plugin\saga\steps;

use core\exception\BusinessException;
use core\plugin\contracts\PluginStore;
use core\plugin\HostSdkCompat;
use core\plugin\PluginManifestValidator;
use core\plugin\PluginPackageInstaller;
use core\plugin\PluginRequirements;
use core\plugin\PluginVersion;
use core\saga\SagaContext;
use support\Log;
use ZipArchive;

/**
 * upgrade saga 第 1 步：在 saga 真正执行时重新校验 staged zip 与被升级的行。
 *
 * 请求里的判定隔着排队窗口，这里逐条重判：staged zip 是否还在、是否被换掉（比对 sha256）、
 * plugins.version 是否被别的操作改过（它是增量区间的下界）。唯一不重读的是 host_sdk_version，用 payload 里冻结的那份。
 */
final class InspectAndValidateStep extends AbstractPluginSagaStep
{
    public function __construct(
        array $saga,
        private readonly PluginStore $pluginRepo,
        private readonly PluginPackageInstaller $installer,
        private readonly PluginManifestValidator $manifestValidator,
    ) {
        parent::__construct($saga);
    }

    public function name(): string
    {
        return 'InspectAndValidate';
    }

    public function execute(SagaContext $ctx): void
    {
        $payload = $this->payload();
        $stagedZip = (string) ($payload['staged_zip'] ?? '');
        $fromVersion = (string) ($payload['from_version'] ?? '');

        if ($stagedZip === '' || !is_file($stagedZip)) {
            throw new BusinessException(
                sprintf(
                    '升级包在排队期间消失：%s（登记这次升级时它还在；请求进程与 saga 进程之间'
                    . '隔着排队窗口，此处必须重判——不重判会在 extract() 上抛一句与真实原因无关的错）',
                    $stagedZip === '' ? '(payload 未记录 staged_zip)' : $stagedZip
                ),
                409
            );
        }
        $this->assertStagedIntegrity($stagedZip, (string) ($payload['staged_hash'] ?? ''));

        $plugin = $this->pluginRepo->find($this->pluginId());
        if ($plugin === null) {
            throw new BusinessException('插件不存在（已在升级排队期间被删除）', 404);
        }
        $currentVersion = (string) ($plugin['version'] ?? '');
        if ($currentVersion !== $fromVersion) {
            throw new BusinessException(
                sprintf(
                    '插件版本在升级排队期间被改动（登记时 %s，此刻 %s）：本次升级的增量区间'
                    . '按登记时的版本算，继续跑会漏跑或重跑迁移',
                    $fromVersion,
                    $currentVersion
                ),
                409
            );
        }

        // inspect() 已经包含 tenant/pc 产物的存在性检查。
        $manifest = $this->installer->inspect($stagedZip);
        $errors = $this->manifestValidator->validate($manifest);
        if ($errors !== []) {
            throw new BusinessException('plugin.json 校验失败: ' . implode('; ', $errors), 422);
        }
        if ((string) $manifest['code'] !== $this->code()) {
            throw new BusinessException("升级 zip 的 code [{$manifest['code']}] 与原插件 [{$this->code()}] 不匹配", 422);
        }
        $toVersion = (string) $manifest['version'];
        if (!PluginVersion::isUpgrade($fromVersion, $toVersion)) {
            throw new BusinessException("新版本 {$toVersion} 不高于旧版本 {$fromVersion}", 422);
        }
        PluginRequirements::assertPlatform(
            $manifest,
            (string) ($payload['core_version'] ?? config('version.version')),
            PluginRequirements::enabledCodes($this->pluginRepo)
        );

        // 兼容闸放在解压之前，拒绝的代价只是一句报错。直接从 zip 读 manifest、不落盘：
        // 解到 /tmp 会把闭源源码树暴露给本机其他用户，还会引入预建路径的攻击面。
        $probeZip = new ZipArchive();
        if ($probeZip->open($stagedZip) !== true) {
            throw new BusinessException('zip 文件无法打开（可能损坏）', 422);
        }
        try {
            HostSdkCompat::assertZipCompatible(
                $probeZip,
                $this->code(),
                (string) ($payload['host_sdk_version'] ?? config('version.host_sdk'))
            );
        } finally {
            $probeZip->close();
        }

        // 后续步骤唯一的 manifest 来源，整条 saga 只 inspect 一次。
        $ctx->set('manifest', $manifest);
        $ctx->set('from_version', $fromVersion);
        $ctx->set('to_version', $toVersion);
    }

    /**
     * 纯校验，补偿只做一件收尾：删掉 staging 里的 zip 拷贝。第一个 step 的补偿在链上最后执行，
     * 此时其余补偿都已跑完。删不掉只记 warning，不抬高退出码。
     */
    public function compensate(SagaContext $ctx): void
    {
        $stagedZip = (string) ($this->payload()['staged_zip'] ?? '');
        if ($stagedZip === '' || !is_file($stagedZip)) {
            return;
        }
        if (!@unlink($stagedZip)) {
            Log::warning('[plugin-upgrade] 升级失败后未能删除暂存的升级包，需人工清理：' . $stagedZip);
        }
    }

    /**
     * staged zip 的完整性校验：与登记时算下的 sha256 比对。
     *
     * 空 hash 必须拒绝，不能像 ExtractZipStep 那样跳过：staged_hash 唯一的生产者算不出来时会直接抛错不排 saga，
     * 走到这里还是空串说明闸门被绕过了。
     */
    private function assertStagedIntegrity(string $stagedZip, string $expectedHash): void
    {
        if ($expectedHash === '') {
            throw new BusinessException(
                sprintf(
                    '升级 saga 缺少登记时的升级包 sha256（payload.staged_hash 为空）：%s 的完整性无从校验，'
                    . '拒绝继续——放行等于让这次升级的完整性闸静默消失',
                    $stagedZip
                ),
                422
            );
        }
        $actual = hash_file('sha256', $stagedZip);
        if ($actual === false || !hash_equals($expectedHash, $actual)) {
            throw new BusinessException(
                "升级包完整性校验失败：{$stagedZip} 的 sha256 与登记时不一致，请重新上传",
                422
            );
        }
    }
}
