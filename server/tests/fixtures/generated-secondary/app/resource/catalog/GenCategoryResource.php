<?php

declare(strict_types=1);

namespace app\resource\catalog;

use core\response\JsonResource;

final class GenCategoryResource extends JsonResource
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
                'name' => ['type' => 'string'],
                'is_featured' => ['type' => 'boolean'],
                'settings' => ['nullable' => true],
                'contact_email' => ['type' => 'string', 'nullable' => true],
                'sort' => ['type' => 'integer'],
                'created_at' => ['type' => 'string', 'nullable' => true],
                'updated_at' => ['type' => 'string', 'nullable' => true],
            ],
        ];
    }
}
