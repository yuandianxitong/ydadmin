<?php
declare(strict_types=1);

namespace core\ai\checks;

/**
 * 检查上下文（只读）。由 AiArtifactService 从 stage 目录构建，喂给各检查。
 */
class CheckContext
{
    /**
     * @param array $manifest 解码后的 manifest.json（含 files[]、entities[]）
     * @param array $entities 每实体元数据（name/table/module/model/route_group/is_main/has_status_switch）
     */
    public function __construct(
        public string $stageDir,
        public array $manifest,
        public array $entities,
        public string $schemaPatch,
        public string $updateSql,
        public array $spec
    ) {
    }

    public function filesDir(): string
    {
        return $this->stageDir . '/files';
    }
}
