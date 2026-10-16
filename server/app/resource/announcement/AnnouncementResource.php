<?php

declare(strict_types=1);

namespace app\resource\announcement;

use core\response\JsonResource;

final class AnnouncementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['id'],
            'additionalProperties' => false,
            'properties' => [
                'id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'content' => ['type' => 'string', 'nullable' => true],
                'type' => ['type' => 'integer'],
                'status' => ['type' => 'integer'],
                'sort' => ['type' => 'integer'],
                'publish_at' => ['type' => 'string', 'nullable' => true],
                'created_by' => ['type' => 'integer', 'nullable' => true],
                'created_at' => ['type' => 'string', 'nullable' => true],
                'updated_at' => ['type' => 'string', 'nullable' => true],
                'deleted_at' => ['type' => 'string', 'nullable' => true],
                'status_text' => ['type' => 'string'],
            ],
        ];
    }
}
