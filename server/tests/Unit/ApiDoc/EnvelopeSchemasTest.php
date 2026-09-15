<?php

declare(strict_types=1);

namespace tests\Unit\ApiDoc;

use core\apidoc\EnvelopeSchemas;
use tests\TestCase;

final class EnvelopeSchemasTest extends TestCase
{
    public function test_all_returns_exactly_the_four_documented_schemas(): void
    {
        $schemas = EnvelopeSchemas::all();

        $this->assertSame(
            [EnvelopeSchemas::SUCCESS, EnvelopeSchemas::ERROR, EnvelopeSchemas::VALIDATION, EnvelopeSchemas::PAGINATED],
            array_keys($schemas),
        );
    }

    public function test_success_and_error_envelopes_share_the_four_top_level_fields(): void
    {
        $schemas = EnvelopeSchemas::all();

        foreach ([EnvelopeSchemas::SUCCESS, EnvelopeSchemas::ERROR, EnvelopeSchemas::VALIDATION, EnvelopeSchemas::PAGINATED] as $name) {
            $this->assertSame('object', $schemas[$name]['type'], "{$name}.type");
            $this->assertSame(
                ['code', 'message', 'data', 'timestamp'],
                $schemas[$name]['required'],
                "{$name}.required",
            );
            $this->assertSame('integer', $schemas[$name]['properties']['code']['type'], "{$name}.properties.code.type");
            $this->assertSame('string', $schemas[$name]['properties']['message']['type'], "{$name}.properties.message.type");
            $this->assertSame('integer', $schemas[$name]['properties']['timestamp']['type'], "{$name}.properties.timestamp.type");
        }
    }

    public function test_validation_error_data_shape_is_field_to_first_message(): void
    {
        $schema = EnvelopeSchemas::all()[EnvelopeSchemas::VALIDATION];
        $errors = $schema['properties']['data']['properties']['errors'];

        $this->assertSame(422, $schema['properties']['code']['example']);
        $this->assertSame(['errors'], $schema['properties']['data']['required']);
        $this->assertSame('object', $errors['type']);
        $this->assertSame(['type' => 'string'], $errors['additionalProperties']);
    }

    public function test_paginated_data_shape_matches_the_response_contract(): void
    {
        $schema = EnvelopeSchemas::all()[EnvelopeSchemas::PAGINATED];
        $data = $schema['properties']['data'];

        $this->assertSame(['list', 'pagination'], $data['required']);
        $this->assertSame('array', $data['properties']['list']['type']);
        $this->assertSame(
            ['current_page', 'per_page', 'total', 'last_page'],
            $data['properties']['pagination']['required'],
        );
        foreach (['current_page', 'per_page', 'total', 'last_page'] as $field) {
            $this->assertSame('integer', $data['properties']['pagination']['properties'][$field]['type'], $field);
        }
    }
}
