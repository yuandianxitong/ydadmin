<?php

declare(strict_types=1);

namespace tests\Unit\Support;

use core\validation\ValidatorFactory;
use tests\TestCase;

/**
 * lang 键守卫：代码里引用的每个 lang 键都必须在 zh_CN 与 en 两份语言包里定义，
 * 否则界面会直接显示 "business.xxx" 这类键名。
 */
final class LangKeysTest extends TestCase
{
    /** @return array<string, list<string>> key => 引用位置 */
    private function referencedKeys(): array
    {
        $root = dirname(__DIR__, 3);
        $keys = [];
        foreach (['app', 'core'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $source = (string) file_get_contents($file->getPathname());
                preg_match_all("/lang\\(\\s*'([a-z_]+\\.[a-z0-9_]+)'/", $source, $a);
                preg_match_all("/'((?:auth|business|validation|messages)\\.[a-z0-9_]+)'/", $source, $b);
                foreach (array_merge($a[1], $b[1]) as $key) {
                    $keys[$key][] = substr($file->getPathname(), strlen($root) + 1);
                }
            }
        }

        return $keys;
    }

    public function test_every_referenced_key_exists_in_both_locales(): void
    {
        $translator = ValidatorFactory::translator();
        $missing = [];
        foreach ($this->referencedKeys() as $key => $files) {
            foreach (['zh_CN', 'en'] as $locale) {
                if (!$translator->hasForLocale($key, $locale)) {
                    $missing[] = "{$locale}: {$key}（" . implode(', ', array_unique($files)) . '）';
                }
            }
        }

        $this->assertSame([], $missing, "缺少以下 lang 键：\n" . implode("\n", $missing));
    }
}
