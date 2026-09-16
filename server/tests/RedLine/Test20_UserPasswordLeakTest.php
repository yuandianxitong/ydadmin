<?php

declare(strict_types=1);

namespace tests\RedLine;

use support\Db;
use tests\Support\ApiTestCase;

/**
 * 红线（spec §2.1「User 模型」：password 字段必须 $hidden；§8）：口令哈希绝不能出现在任何响应体里，
 * 不管是顶层键还是嵌套在 data 内部任意深度的键。C 端与管理端各两个会吐出「用户整行」的出口一起钉：
 * C 端 auth/info、C 端 user/profile、管理端 user/list、管理端 user/detail/{id}。
 */
final class Test20_UserPasswordLeakTest extends ApiTestCase
{
    public function test_password_never_appears_in_any_response(): void
    {
        $hash = password_hash('Rl-Passw0rd!20', PASSWORD_DEFAULT);
        $user = $this->actingAsUser(['password' => $hash]);
        $stored = (string) Db::table('users')->where('id', $user->id)->value('password');
        $this->assertSame($hash, $stored, '前置条件：用户行必须存了刚才那个密码哈希');
        $admin = $this->actingAsAdmin(['user.list', 'user.detail']);

        $responses = [
            'C 端 auth/info'     => $this->get('/api/auth/info', [], $user->token)->assertOk(),
            'C 端 user/profile'  => $this->get('/api/user/profile', [], $user->token)->assertOk(),
            '管理端 user/list'   => $this->get('/adminapi/user/list', ['page' => 1, 'limit' => 10], $admin->token)->assertOk(),
            '管理端 user/detail' => $this->get("/adminapi/user/detail/{$user->id}", [], $admin->token)->assertOk(),
        ];

        foreach ($responses as $context => $response) {
            $this->assertStringNotContainsString($hash, $response->body(), "{$context}：响应体原文出现了密码哈希");
            $this->assertNoPasswordLeak($response->json(), $context);
        }
    }

    /** 递归扫响应体：任何深度出现字符串键 'password' 都判定为泄露，不能只看顶层键（spec §2.1）。 */
    private function assertNoPasswordLeak(mixed $value, string $context, string $path = ''): void
    {
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $this->assertNotSame('password', $key, "{$context}：路径 {$path}.{$key} 泄露了 password 字段");
            }
            $this->assertNoPasswordLeak($item, $context, $path . '.' . (is_string($key) ? $key : (string) $key));
        }
    }
}
