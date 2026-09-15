<?php

declare(strict_types=1);

namespace tests\Unit\ApiDoc;

use core\apidoc\EndpointDescriptor;
use tests\TestCase;

final class EndpointDescriptorTest extends TestCase
{
    public function test_operation_id_uses_short_controller_name_and_action(): void
    {
        $endpoint = new EndpointDescriptor(
            method: 'GET',
            path: '/adminapi/system/dictionary/{id:\d+}',
            controller: 'app\\adminapi\\controller\\system\\DictionaryController',
            action: 'show',
            permission: 'system.dictionary.list',
            permissionSkipped: false,
            tag: 'system',
            requiresAuth: true,
        );

        $this->assertSame('DictionaryController::show', $endpoint->operationId());
        $this->assertTrue($endpoint->requiresAuth);
    }

    public function test_path_parameters_maps_numeric_regex_to_integer(): void
    {
        $endpoint = new EndpointDescriptor('GET', '/adminapi/system/dictionary/{id:\d+}', 'C', 'show', null, false, 'system', true);

        $this->assertSame([['name' => 'id', 'type' => 'integer']], $endpoint->pathParameters());
    }

    public function test_path_parameters_maps_missing_or_non_numeric_regex_to_string(): void
    {
        $bare = new EndpointDescriptor('GET', '/adminapi/widget/{code}', 'C', 'show', null, false, 'widget', true);
        $letters = new EndpointDescriptor('GET', '/adminapi/widget/{code:[a-z]+}', 'C', 'show', null, false, 'widget', true);

        $this->assertSame([['name' => 'code', 'type' => 'string']], $bare->pathParameters());
        $this->assertSame([['name' => 'code', 'type' => 'string']], $letters->pathParameters());
    }

    public function test_path_parameters_handles_multiple_segments_in_one_path(): void
    {
        $endpoint = new EndpointDescriptor('PUT', '/adminapi/system/dictionary/item/{id:\d+}', 'C', 'updateItem', null, false, 'system', true);

        $this->assertSame([['name' => 'id', 'type' => 'integer']], $endpoint->pathParameters());
    }

    public function test_expects_body_is_true_only_for_post_put_patch(): void
    {
        $get = new EndpointDescriptor('GET', '/adminapi/x', 'C', 'index', null, true, 'x', true);
        $post = new EndpointDescriptor('POST', '/adminapi/x', 'C', 'store', null, true, 'x', true);
        $put = new EndpointDescriptor('PUT', '/adminapi/x/{id:\d+}', 'C', 'update', null, true, 'x', true);
        $delete = new EndpointDescriptor('DELETE', '/adminapi/x/{id:\d+}', 'C', 'delete', null, true, 'x', true);

        $this->assertFalse($get->expectsBody());
        $this->assertTrue($post->expectsBody());
        $this->assertTrue($put->expectsBody());
        $this->assertFalse($delete->expectsBody());
    }

    public function test_rules_method_is_action_name_suffixed_with_rules(): void
    {
        $endpoint = new EndpointDescriptor('POST', '/adminapi/system/dictionary', 'C', 'store', null, false, 'system', true);

        $this->assertSame('storeRules', $endpoint->rulesMethod());
    }
}
