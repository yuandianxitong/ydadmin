<?php
// server/core/ai/FeedbackReporter.php
declare(strict_types=1);

namespace core\ai;

/**
 * 生成结果反馈上报（严格 opt-in：首次询问，选择持久化；任何失败静默）
 */
class FeedbackReporter
{
    public function __construct(
        protected YdConfig $config,
        protected AiClient $client,
    ) {
    }

    public function resolveOptin(callable $ask): bool
    {
        $optin = $this->config->get('feedback_optin');
        if ($optin === null) {
            $optin = (bool) $ask('是否允许匿名上报生成结果的接受/拒绝信号以帮助改进生成质量？（仅上报动作，不上报你的代码）');
            $this->config->set('feedback_optin', $optin);
        }
        return (bool) $optin;
    }

    public function report(string $generationId, string $action): void
    {
        if ($this->config->get('feedback_optin') !== true) {
            return;
        }
        try {
            $this->client->feedback($generationId, $action);
        } catch (\Throwable $e) {
            trace('AI 反馈上报失败（已忽略）：' . $e->getMessage(), 'debug');
        }
    }
}
