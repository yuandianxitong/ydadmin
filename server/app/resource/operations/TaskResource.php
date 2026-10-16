<?php

declare(strict_types=1);

namespace app\resource\operations;

use core\response\JsonResource;

final class TaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public static function schema(): array
    {
        return [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['task_id', 'kind', 'module', 'status', 'processed', 'success_count', 'fail_count', 'errors', 'result', 'download_ready'],
            'properties' => [
                'task_id' => ['type' => 'string'], 'kind' => ['type' => 'string', 'enum' => ['import', 'export']],
                'module' => ['type' => 'string'], 'status' => ['type' => 'string', 'enum' => ['queued', 'running', 'completed', 'failed', 'cancelled']],
                'processed' => ['type' => 'integer'], 'success_count' => ['type' => 'integer'], 'fail_count' => ['type' => 'integer'],
                'errors' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['row' => ['type' => 'integer'], 'message' => ['type' => 'string']]]],
                'result' => ['type' => 'object', 'properties' => ['import_id' => ['type' => 'integer'], 'errors_truncated' => ['type' => 'boolean']]],
                'download_ready' => ['type' => 'boolean'],
            ],
        ];
    }
}
