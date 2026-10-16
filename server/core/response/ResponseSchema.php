<?php

declare(strict_types=1);

namespace core\response;

#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class ResponseSchema
{
    /** @param class-string<JsonResource> $resource */
    public function __construct(public string $resource, public bool $paginated = false)
    {
        if (!is_subclass_of($resource, JsonResource::class)) {
            throw new \InvalidArgumentException('响应资源必须继承 JsonResource');
        }
    }

    /** @return array<string, mixed> */
    public function dataSchema(): array
    {
        $schema = $this->resource::schema();
        if (!$this->paginated) {
            return $schema;
        }
        return [
            'type' => 'object',
            'required' => ['list', 'pagination'],
            'properties' => [
                'list' => ['type' => 'array', 'items' => $schema],
                'pagination' => \core\response\EnvelopeSchemas::all()[\core\response\EnvelopeSchemas::PAGINATED]['properties']['data']['properties']['pagination'],
            ],
        ];
    }
}
