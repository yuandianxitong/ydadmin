<?php

declare(strict_types=1);

namespace tests\Feature\Feedback;

use support\Db;
use tests\Support\ApiTestCase;

final class FeedbackApiTest extends ApiTestCase
{
    /** content 是 text（64KB）、images 是 json：没有上限时超长内容是 500 而不是 422。 */
    public function test_submit_rejects_oversized_content_and_too_many_images(): void
    {
        $user = $this->actingAsUser();

        $this->post('/api/feedback/submit', ['content' => str_repeat('长', 2001)], $user->token)->assertCode(422);
        $this->post('/api/feedback/submit', [
            'content' => 'ok',
            'images'  => array_fill(0, 10, 'https://example.com/a.png'),
        ], $user->token)->assertCode(422);

        $this->assertSame(0, (int) Db::table('feedbacks')->where('user_id', $user->id)->count());
    }

    /** 一个会员能无限刷反馈的话，每次都会连带写一条站内信和一条消息日志。 */
    public function test_submit_is_rate_limited_per_user(): void
    {
        $user = $this->actingAsUser();
        $limit = max(1, (int) config('feedback.submit_per_minute', 5));

        for ($i = 0; $i < $limit; $i++) {
            $ok = $this->post('/api/feedback/submit', ['content' => "第{$i}条"], $user->token)->assertOk()->data();
            $this->track('feedbacks', (int) $ok['id']);
        }

        $this->post('/api/feedback/submit', ['content' => '再来一条'], $user->token)->assertCode(429);
        $this->assertSame($limit, (int) Db::table('feedbacks')->where('user_id', $user->id)->count());
    }

    public function test_user_submits_and_receives_site_message_admin_can_reply(): void
    {
        $user = $this->actingAsUser();
        $other = $this->actingAsUser();
        $created = $this->post('/api/feedback/submit', ['content' => 'hello', 'type' => 'bug'], $user->token)->assertOk()->data();
        $this->track('feedbacks', (int) $created['id']);
        $this->assertSame(0, (int) $created['status']);
        $this->assertSame($user->id, (int) $created['user_id']);

        $note = Db::table('user_notifications')->where('user_id', $user->id)->where('type', 'feedback')->where('biz_id', (string) $created['id'])->first();
        $this->assertNotNull($note);
        $this->track('user_notifications', (int) $note->id);

        $this->get('/api/feedback/detail/'.$created['id'], [], $other->token)->assertCode(404);
        $mine = $this->get('/api/feedback/detail/'.$created['id'], [], $user->token)->assertOk()->data();
        $this->assertSame('hello', $mine['content']);

        $admin = $this->actingAsAdmin('super');
        $this->post('/adminapi/feedback/reply', ['id' => $created['id'], 'reply' => 'ok'], $admin->token)->assertOk();
        $row = Db::table('feedbacks')->where('id', $created['id'])->first();
        $this->assertSame(2, (int) $row->status);
        $this->assertSame($admin->id, (int) $row->replied_by);
        $this->post('/adminapi/feedback/close/'.$created['id'], [], $admin->token)->assertOk();
        $this->post('/adminapi/feedback/reply', ['id' => $created['id'], 'reply' => 'no'], $admin->token)->assertCode(400);
    }

    public function test_guest_cannot_create_feedback_via_admin_or_api(): void
    {
        $this->post('/api/feedback/submit', ['content' => 'x'])->assertCode(401);
        $this->assertSame(0, Db::table('feedbacks')->where('content', 'x')->count());
        $admin = $this->actingAsAdmin('super');
        $missing = $this->post('/adminapi/feedback', ['content' => 'nope'], $admin->token);
        $this->assertSame(404, $missing->status(), '管理端不得注册反馈创建路由');
        $missing->assertCode(404);
    }
}
