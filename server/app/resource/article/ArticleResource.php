<?php

declare(strict_types=1);

namespace app\resource\article;

use core\response\JsonResource;

final class ArticleResource extends JsonResource
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
                'category_id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'cover' => ['type' => 'string'],
                'summary' => ['type' => 'string'],
                'content' => ['type' => 'string'],
                'tags' => ['type' => 'array', 'items' => [], 'nullable' => true],
                'author' => ['type' => 'string'],
                'view_count' => ['type' => 'integer'],
                'status' => ['type' => 'integer'],
                'publish_at' => ['type' => 'string', 'nullable' => true],
                'created_by' => ['type' => 'integer', 'nullable' => true],
                'created_at' => ['type' => 'string', 'nullable' => true],
                'updated_at' => ['type' => 'string', 'nullable' => true],
                'deleted_at' => ['type' => 'string', 'nullable' => true],
                'status_text' => ['type' => 'string'],
                'category_name' => ['type' => 'string'],
                'views' => ['type' => 'integer'],
            ],
        ];
    }
}
