<?php

declare(strict_types=1);

namespace tests\Support;

final class ImageFixture
{
    /** 有效的 1×1 PNG；可附加填充以测试字节大小限制。 */
    public static function png(int $padding = 0): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC')
            . str_repeat("\0", $padding);
    }
}
