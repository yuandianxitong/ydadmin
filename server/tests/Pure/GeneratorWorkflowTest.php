<?php

declare(strict_types=1);

namespace tests\Pure;

use core\generator\CommentEncoding;
use core\generator\GenerationGuide;
use core\generator\GeneratorRequest;
use PHPUnit\Framework\TestCase;

final class GeneratorWorkflowTest extends TestCase
{
    public function test_comment_detection_preserves_normal_multilingual_labels_and_rejects_encoding_damage(): void
    {
        foreach (['业务工单', '标题', '生成器夹具表', "用户's 列表", '状态 0=禁用 1=正常', '价格 €', 'Résumé', '确认?', '支持 emoji 🎉'] as $text) {
            self::assertFalse(CommentEncoding::isSuspicious($text), $text);
        }
        foreach (["坏\xff", '字段�', '????', 'ç¤ºä¾‹', mb_convert_encoding('示例', 'UTF-8', 'GB18030')] as $text) {
            self::assertTrue(CommentEncoding::isSuspicious($text), bin2hex($text));
        }
    }

    public function test_generation_guide_uses_real_artifact_names_and_container_commands(): void
    {
        $guide = GenerationGuide::forRequest(new GeneratorRequest('biz_tickets', 'biz', 'BizTicket', '', []));
        self::assertSame('server/database/generated/biz-biz_ticket-menu.sql', $guide['menu_sql']);
        self::assertSame('/admin/biz/biz-ticket', $guide['route']);
        self::assertStringContainsString('--default-character-set=utf8mb4', $guide['manual'][0]);
        self::assertStringContainsString($guide['menu_sql'], $guide['docker'][0]);
        self::assertStringContainsString('MYSQL_PWD="$MYSQL_PASSWORD"', $guide['docker'][0]);
        self::assertStringContainsString('exec -T webman php start.php reload', $guide['docker'][1]);
        self::assertSame('pnpm --dir admin build', $guide['docker'][2]);
    }
}
