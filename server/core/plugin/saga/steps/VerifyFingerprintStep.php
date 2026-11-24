<?php

declare(strict_types=1);

namespace core\plugin\saga\steps;

use core\exception\BusinessException;
use core\plugin\saga\ReloadVerifier;
use core\saga\SagaContext;

/**
 * install saga 第 8 步：轮询 worker 指纹注册表，直到全池收敛到期望指纹。
 *
 * 超时抛异常，整条 saga 逆序补偿。
 */
final class VerifyFingerprintStep extends AbstractPluginSagaStep
{
    public function __construct(array $saga, private readonly ReloadVerifier $verifier)
    {
        parent::__construct($saga);
    }

    public function name(): string
    {
        return 'VerifyFingerprint';
    }

    public function execute(SagaContext $ctx): void
    {
        $expected = (string) $ctx->get('expected_fingerprint', '');
        if ($expected === '') {
            // 不能拿空串去比对：空期望值遇上空注册表会被判成假的“已生效”。
            throw new BusinessException('saga 上下文缺少 expected_fingerprint，无法校验 reload 是否生效', 500);
        }
        $ctx->set('observed_fingerprint', $this->verifier->waitUntilEffective($expected));
    }

    /** 纯观测，无副作用；补偿后的重新收敛由 `PluginSagaRunner::settle()` 统一做。 */
    public function compensate(SagaContext $ctx): void
    {
    }
}
