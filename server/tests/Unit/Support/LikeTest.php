<?php

declare(strict_types=1);

namespace tests\Unit\Support;

use core\support\Like;
use tests\TestCase;

final class LikeTest extends TestCase
{
    public function test_wildcards_and_the_escape_char_are_escaped(): void
    {
        $this->assertSame('%abc%', Like::contains('abc'));
        $this->assertSame('%100\%%', Like::contains('100%'));
        $this->assertSame('%a\_b%', Like::contains('a_b'));
        $this->assertSame('%C:\\\\dir%', Like::contains('C:\\dir'));
        $this->assertSame('%%', Like::contains(''));
    }

    /**
     * 守卫：app/、core/ 里不允许再手拼 LIKE 通配串（'%' . $x . '%'、"%{$x}%"），一律经 Like::contains()。
     * 新增的 keyword 搜索（含后续任务）漏转义时这里会红。
     */
    public function test_no_hand_built_like_patterns_left_in_app_or_core(): void
    {
        $root = dirname(__DIR__, 3);
        $patterns = [
            '/([\'"])%\1\s*\./',  // '%' . $x
            '/\.\s*([\'"])%\1/',  // $x . '%'
            '/"%\{?\$[^"]*%"/',   // "%{$x}%"、"%$x%"
        ];
        $offenders = [];
        foreach (['app', 'core'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $relative = substr($file->getPathname(), strlen($root) + 1);
                if ($file->getExtension() !== 'php' || $relative === 'core/support/Like.php') {
                    continue;
                }
                foreach ((array) file($file->getPathname()) as $index => $line) {
                    foreach ($patterns as $pattern) {
                        if (preg_match($pattern, (string) $line) === 1) {
                            $offenders[] = $relative . ':' . ($index + 1) . '  ' . trim((string) $line);
                            break;
                        }
                    }
                }
            }
        }

        $this->assertSame([], $offenders, "以下 LIKE 通配串没有经 Like::contains() 转义：\n" . implode("\n", $offenders));
    }
}
