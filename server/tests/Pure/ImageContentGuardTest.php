<?php

declare(strict_types=1);

namespace tests\Pure;

use core\storage\ImageContentGuard;
use PHPUnit\Framework\TestCase;
use tests\Support\ImageFixture;

final class ImageContentGuardTest extends TestCase
{
    public function test_checks_actual_bytes_and_matches_extension(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'image-guard-');
        try {
            file_put_contents($path, ImageFixture::png());
            self::assertSame('image/png', ImageContentGuard::detect($path, 'PNG'));
            self::assertNull(ImageContentGuard::detect($path, 'jpg'));
            self::assertNull(ImageContentGuard::detect($path, 'php'));

            foreach (['<?php echo "pwned";', '<script>alert(1)</script>', "\x89PNG\r\n\x1a\n", ''] as $bytes) {
                file_put_contents($path, $bytes);
                self::assertNull(ImageContentGuard::detect($path, 'png'));
            }
        } finally {
            unlink($path);
        }
    }
}
