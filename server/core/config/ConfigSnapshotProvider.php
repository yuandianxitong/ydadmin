<?php

declare(strict_types=1);

namespace core\config;

/** 一次驱动解析使用同一份已提交配置，避免切换供应商时拼出新旧混合凭据。 */
interface ConfigSnapshotProvider extends ConfigValueReader
{
    public function snapshot(): \core\config\ConfigSnapshot;
}
