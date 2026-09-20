<?php

declare(strict_types=1);

namespace tests\RedLine;

use support\Db;
use tests\Support\ApiTestCase;

/**
 * 红线（M7a）：C 端提交反馈必须登录。未带 token 的 POST 必须 401，
 * 且不得按请求体 content 落库（不能「先写再拒」）。
 */
final class Test44_FeedbackSubmitRequiresAuthTest extends ApiTestCase
{
    public function test_unauthenticated_submit_does_not_create_row(): void
    {
        $unique = 'rl44-' . bin2hex(random_bytes(8));

        $this->post('/api/feedback/submit', ['content' => $unique])->assertCode(401);
        $this->assertSame(0, Db::table('feedbacks')->where('content', $unique)->count());
    }
}
