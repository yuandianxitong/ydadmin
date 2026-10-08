<?php

declare(strict_types=1);

namespace tests\Unit\Generator;

use core\generator\TemplateRenderer;
use tests\Support\GoldenFile;
use tests\TestCase;

final class LangStubTest extends TestCase
{
    use GenArticleFixtureColumns;
    use GoldenFile;

    public function test_zh_cn_renders_byte_identical_to_golden_fixture(): void
    {
        $this->assertRendersToFixture('zh_CN', 'zh_CN/demo/gen_article.php');
    }

    public function test_en_renders_byte_identical_to_golden_fixture(): void
    {
        $this->assertRendersToFixture('en', 'en/demo/gen_article.php');
    }

    public function test_zh_cn_and_en_have_identical_key_sets(): void
    {
        $renderer = new TemplateRenderer(base_path() . '/core/generator/stubs');

        $zhKeys = array_keys($this->renderToArray($renderer, 'zh_CN'));
        $enKeys = array_keys($this->renderToArray($renderer, 'en'));
        sort($zhKeys);
        sort($enKeys);

        $this->assertNotEmpty($zhKeys);
        $this->assertSame($zhKeys, $enKeys, 'zh_CN 与 en 的语言键集合必须完全一致');
    }

    private function assertRendersToFixture(string $locale, string $fixtureRelativePath): void
    {
        $renderer = new TemplateRenderer(base_path() . '/core/generator/stubs');
        $content = $renderer->render('lang.stub.php', self::genArticleVars($locale));

        $this->assertGoldenFile('resource/lang/' . $fixtureRelativePath, $content);
    }

    /** @return array<string, string> */
    private function renderToArray(TemplateRenderer $renderer, string $locale): array
    {
        $content = $renderer->render('lang.stub.php', self::genArticleVars($locale));

        $tmpFile = tempnam(sys_get_temp_dir(), 'gen_lang_') . '.php';
        file_put_contents($tmpFile, $content);
        /** @var array<string, string> $array */
        $array = require $tmpFile;
        unlink($tmpFile);

        return $array;
    }
}
