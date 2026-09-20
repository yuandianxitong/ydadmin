<?php

declare(strict_types=1);

namespace tests\RedLine;

use support\Db;
use tests\Support\ApiTestCase;

/**
 * 红线（M7a）：C 端反馈详情只能看本人。用户 B 打用户 A 的详情必须 404，
 * 响应 data 不得带 content / reply（他人正文与回复不得泄漏）。
 */
final class Test43_FeedbackCrossUserInvisibleTest extends ApiTestCase
{
    public function test_other_user_cannot_read_feedback_detail(): void
    {
        $owner = $this->actingAsUser();
        $stranger = $this->actingAsUser();
        $content = 'rl43-' . bin2hex(random_bytes(8));

        $created = $this->post('/api/feedback/submit', [
            'content' => $content,
            'type'    => 'bug',
        ], $owner->token)->assertOk()->data();
        $this->track('feedbacks', (int) $created['id']);

        $note = Db::table('user_notifications')
            ->where('user_id', $owner->id)
            ->where('type', 'feedback')
            ->where('biz_id', (string) $created['id'])
            ->first();
        if ($note !== null) {
            $this->track('user_notifications', (int) $note->id);
        }

        $foreign = $this->get('/api/feedback/detail/' . $created['id'], [], $stranger->token);
        $foreign->assertCode(404);
        $data = $foreign->data();
        $this->assertArrayNotHasKey('content', $data);
        $this->assertArrayNotHasKey('reply', $data);
    }
}
