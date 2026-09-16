<?php

declare(strict_types=1);

namespace tests\Support\Wechat;

use support\Db;
use support\Redis;

/**
 * 微信登录用例的夹具（M6a Task 5 起复用）。只能用在 tests\Support\ApiTestCase 的子类里（依赖 setConfig / trackUser）。
 *
 * 所有 openid、unionid、手机号都是随机值并登记在本 trait 里：cleanupWechatFixtures() 按登记值兜底找出被测代码
 * 自己注册出来的用户（用例拿不到它们的 id），交给 trackUser() 在父类 tearDown 里删掉；同时清掉 access_token
 * 缓存与登录锁，避免残留影响下一次运行。
 */
trait WechatUserFixtures
{
    /** @var list<string> */
    private array $fixtureOpenids = [];

    /** @var list<string> */
    private array $fixtureMobiles = [];

    /** @var list<string> */
    private array $fixtureAppIds = [];

    private function fixtureOpenid(string $prefix = 'o'): string
    {
        $value = $prefix . bin2hex(random_bytes(12));
        $this->fixtureOpenids[] = $value;

        return $value;
    }

    private function fixtureMobile(): string
    {
        $value = '139' . str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);
        $this->fixtureMobiles[] = $value;

        return $value;
    }

    /** 三端都配上随机 appid / secret；setConfig 会在 tearDown 恢复原值。 */
    private function configureWechatApps(): void
    {
        foreach (['mini', 'official', 'open'] as $app) {
            $appId = 'wx' . bin2hex(random_bytes(8));
            $this->fixtureAppIds[] = $appId;
            $this->setConfig("wechat_{$app}_app_id", $appId);
            $this->setConfig("wechat_{$app}_app_secret", 'secret-' . bin2hex(random_bytes(8)));
        }
    }

    /** @param array<string, mixed> $attributes */
    private function insertWechatUser(array $attributes): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('users')->insertGetId(array_merge([
            'nickname'   => 'wx_fixture',
            'status'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $attributes));
        $this->trackUser($id);

        return $id;
    }

    /** @return array<string, mixed>|null */
    private function userRow(int $id): ?array
    {
        $row = Db::table('users')->where('id', $id)->first();

        return $row === null ? null : (array) $row;
    }

    private function cleanupWechatFixtures(): void
    {
        $values = $this->fixtureOpenids === [] ? ['-'] : $this->fixtureOpenids;
        $mobiles = $this->fixtureMobiles === [] ? ['-'] : $this->fixtureMobiles;
        $ids = Db::table('users')
            ->where(static function ($query) use ($values, $mobiles): void {
                $query->whereIn('openid', $values)
                    ->orWhereIn('mini_openid', $values)
                    ->orWhereIn('oa_openid', $values)
                    ->orWhereIn('unionid', $values)
                    ->orWhereIn('mobile', $mobiles);
            })
            ->pluck('id')
            ->all();
        foreach ($ids as $id) {
            $this->trackUser((int) $id);
        }

        foreach ($this->fixtureAppIds as $appId) {
            Redis::del("wechat:access_token:{$appId}", "wechat:access_token_lock:{$appId}");
        }
        foreach ($this->fixtureOpenids as $openid) {
            Redis::del("wechat:login_lock:openid:{$openid}", "wechat:login_lock:mini_openid:{$openid}", "wechat:login_lock:oa_openid:{$openid}");
        }

        $this->fixtureOpenids = [];
        $this->fixtureMobiles = [];
        $this->fixtureAppIds = [];
    }
}
