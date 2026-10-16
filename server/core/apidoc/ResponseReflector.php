<?php

declare(strict_types=1);

namespace core\apidoc;

use core\response\ResponseSchema;

final class ResponseReflector
{
    /** @return array<string, mixed>|null */
    public function schemaFor(string $controller, string $action): ?array
    {
        $attributes = (new \ReflectionMethod($controller, $action))->getAttributes(ResponseSchema::class);

        return $attributes === [] ? null : $attributes[0]->newInstance()->dataSchema();
    }
}
