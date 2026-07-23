<?php
// server/app/service/system/YdSpecService.php
declare(strict_types=1);

namespace app\service\system;

use core\ai\AiClient;
use core\ai\ProjectContext;
use core\ai\YdConfig;
use core\ai\ydspec\YdSpecValidator;
use core\base\Service;
use core\exception\BusinessException;

class YdSpecService extends Service
{
    protected YdSpecValidator $ydSpecValidator;

    protected function makeClient(): AiClient
    {
        $token = env('YD_AI_TOKEN') ?: (new YdConfig())->get('token');
        return new AiClient((string) config('ai.endpoint'), $token ?: null, (int) config('ai.timeout'));
    }

    protected function specsBase(): string
    {
        return rtrim(root_path(), '/') . '/runtime/ai/specs';
    }

    public function refine(string $description, array $answers, ?array $draft): array
    {
        $projectId = (new ProjectContext())->id();
        $result = $this->makeClient()->specRefine($description, $answers, $draft, $projectId);
        $draftSpec = $result['draft_spec'] ?? [];

        $structural = array_map(
            static fn (string $m): array => ['ref' => '', 'rule' => 'structure', 'severity' => 'error', 'message' => $m],
            $this->ydSpecValidator->validateStructure($draftSpec)
        );
        $issues = array_merge($structural, $this->ydSpecValidator->validateSemantics($draftSpec));

        return [
            'draft_spec'   => $draftSpec,
            'questions'    => $result['questions'] ?? [],
            'explanations' => $result['explanations'] ?? [],
            'issues'       => $issues,
        ];
    }

    public function confirm(array $spec): array
    {
        $errors = $this->ydSpecValidator->validateStructure($spec);
        if ($errors) {
            throw new BusinessException('规格结构校验未通过：' . implode('；', $errors));
        }
        $blocking = array_filter(
            $this->ydSpecValidator->validateSemantics($spec),
            static fn (array $i): bool => $i['severity'] === 'error'
        );
        if ($blocking) {
            throw new BusinessException('规格语义校验未通过：' . implode('；', array_column($blocking, 'message')));
        }

        $specId = 'spec_' . bin2hex(random_bytes(8));
        $dir = $this->specsBase() . '/' . $specId;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(
            $dir . '/ydspec.json',
            json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        return ['spec_id' => $specId, 'path' => 'runtime/ai/specs/' . $specId . '/ydspec.json'];
    }
}
