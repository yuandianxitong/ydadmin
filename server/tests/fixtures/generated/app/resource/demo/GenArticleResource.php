<?php

declare(strict_types=1);

namespace app\resource\demo;

use core\response\JsonResource;

final class GenArticleResource extends JsonResource
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
                'summary' => ['type' => 'string', 'nullable' => true],
                'content' => ['type' => 'string', 'nullable' => true],
                'cover_image' => ['type' => 'string', 'nullable' => true],
                'category' => ['type' => 'string', 'enum' => ['news', 'tech', 'life']],
                'price' => ['type' => 'string'],
                'view_count' => ['type' => 'integer'],
                'slug' => ['type' => 'string'],
                'published_at' => ['type' => 'string', 'nullable' => true],
                'status' => ['type' => 'integer'],
                'sort' => ['type' => 'integer'],
                'created_by' => ['type' => 'integer', 'nullable' => true],
                'dept_id' => ['type' => 'integer', 'nullable' => true],
                'created_at' => ['type' => 'string', 'nullable' => true],
                'updated_at' => ['type' => 'string', 'nullable' => true],
                'deleted_at' => ['type' => 'string', 'nullable' => true],
                'status_text' => ['type' => 'string'],
            ],
        ];
    }
}
