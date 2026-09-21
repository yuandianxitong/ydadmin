<?php

declare(strict_types=1);

namespace tests\Unit\Diy;

use core\diy\DiyWidgetRegistry;
use core\exception\ValidationException;
use tests\TestCase;

final class DiyWidgetRegistryTest extends TestCase
{
    public function test_types_are_the_fifteen_builtins(): void
    {
        $this->assertSame([
            'banner', 'nav-grid', 'category-nav', 'rich-text', 'title-bar', 'divider',
            'image-ad', 'image-cube', 'video', 'notice', 'search-bar', 'float-button',
            'user-info-card', 'service-menu', 'content-list',
        ], DiyWidgetRegistry::TYPES);
    }

    public function test_validate_keeps_hidden_and_rejects_dup_id(): void
    {
        $reg = new DiyWidgetRegistry();
        $ok = $reg->validate([
            ['id' => 'a', 'type' => 'banner', 'props' => ['h' => 1], 'hidden' => true],
        ]);
        $this->assertTrue($ok[0]['hidden']);

        try {
            $reg->validate([
                ['id' => 'a', 'type' => 'banner', 'props' => []],
                ['id' => 'a', 'type' => 'notice', 'props' => []],
            ]);
            $this->fail('expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('components', $e->errors());
        }
    }

    public function test_validate_rejects_unknown_type_and_empty_id(): void
    {
        $reg = new DiyWidgetRegistry();
        foreach ([
            [['id' => '', 'type' => 'banner', 'props' => []]],
            [['id' => 'x', 'type' => 'not-a-widget', 'props' => []]],
            [['id' => 'x', 'type' => 'banner', 'props' => 'bad']],
        ] as $input) {
            try {
                $reg->validate($input);
                $this->fail('expected ValidationException');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('components', $e->errors());
            }
        }
    }
}
