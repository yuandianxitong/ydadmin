<?php

declare(strict_types=1);

namespace tests\Unit\Wechat;

use app\service\wechat\OfficialAccountService;
use core\contract\ConfigValueReader;
use core\exception\BusinessException;
use core\exception\ValidationException;
use core\wechat\AccessTokenProvider;
use core\wechat\OfficialAccountApi;
use core\wechat\WechatConfigResolver;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use support\Redis;
use tests\Support\Wechat\FakeWechatHttp;
use tests\TestCase;

final class OfficialAccountServiceTest extends TestCase
{
    use FakeWechatHttp;

    private string $appId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->appId = 'wxmenu' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        try {
            Redis::del('wechat:access_token:' . $this->appId, 'wechat:access_token_lock:' . $this->appId);
        } finally {
            parent::tearDown();
        }
    }

    /** @param list<\Psr\Http\Message\ResponseInterface|\Throwable> $responses */
    private function service(array $responses = [], bool $configured = true): OfficialAccountService
    {
        $http = $this->wechatClient($responses);
        $values = $configured ? [
            'wechat_official_app_id'     => $this->appId,
            'wechat_official_app_secret' => 'SECRET-official-app',
        ] : [];
        $configs = new WechatConfigResolver(new class ($values) implements ConfigValueReader {
            /** @param array<string, mixed> $values */
            public function __construct(private readonly array $values)
            {
            }

            public function getConfigValue(string $key, mixed $default = null): mixed
            {
                return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
            }
        });
        $api = new OfficialAccountApi($http, new AccessTokenProvider($http), $configs);

        return new OfficialAccountService($api);
    }

    private function cacheToken(): void
    {
        Redis::set('wechat:access_token:' . $this->appId, 'AT-MENU-CACHED', 'EX', 600);
    }

    /** @param array<mixed> $button */
    private function assertInvalid(array $button): void
    {
        try {
            $this->service()->createMenu($button);
            $this->fail('非法菜单必须抛 ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(['button' => lang('wechat.menu_invalid')], $e->errors());
            $this->assertSame([], $this->wechatRequests());
        }
    }

    public function test_rejects_invalid_top_level_button_counts(): void
    {
        $this->assertInvalid([]);
        $this->assertInvalid([
            ['name' => 'a', 'type' => 'click', 'key' => 'a'],
            ['name' => 'b', 'type' => 'click', 'key' => 'b'],
            ['name' => 'c', 'type' => 'click', 'key' => 'c'],
            ['name' => 'd', 'type' => 'click', 'key' => 'd'],
        ]);
    }

    /** @return array<string, array{array<mixed>}> */
    public static function invalidMenus(): array
    {
        return [
            '顶级菜单必须是对象' => [[['not-an-object']]],
            '名称必填' => [[['type' => 'click', 'key' => 'k']]],
            '名称最长 16 字符' => [[['name' => str_repeat('n', 17), 'type' => 'click', 'key' => 'k']]],
            '子菜单必须是数组' => [[['name' => '父级', 'sub_button' => 'bad']]],
            '最多 5 个子菜单' => [[['name' => '父级', 'sub_button' => array_fill(0, 6, ['name' => '子级', 'type' => 'click', 'key' => 'k'])]]],
            '叶子类型受限' => [[['name' => '菜单', 'type' => 'media_id']]],
            'view 必须有 url' => [[['name' => '菜单', 'type' => 'view']]],
            'view url 最长 500' => [[['name' => '菜单', 'type' => 'view', 'url' => str_repeat('u', 501)]]],
            'click 必须有 key' => [[['name' => '菜单', 'type' => 'click']]],
            'click key 最长 128' => [[['name' => '菜单', 'type' => 'click', 'key' => str_repeat('k', 129)]]],
            'miniprogram 必须有 appid' => [[['name' => '菜单', 'type' => 'miniprogram', 'pagepath' => 'pages/a', 'url' => 'https://x.test']]],
            'miniprogram appid 最长 32' => [[['name' => '菜单', 'type' => 'miniprogram', 'appid' => str_repeat('a', 33), 'pagepath' => 'pages/a', 'url' => 'https://x.test']]],
            'miniprogram 必须有 pagepath' => [[['name' => '菜单', 'type' => 'miniprogram', 'appid' => 'wxapp', 'url' => 'https://x.test']]],
            'miniprogram pagepath 最长 200' => [[['name' => '菜单', 'type' => 'miniprogram', 'appid' => 'wxapp', 'pagepath' => str_repeat('p', 201), 'url' => 'https://x.test']]],
            'miniprogram 必须有 url' => [[['name' => '菜单', 'type' => 'miniprogram', 'appid' => 'wxapp', 'pagepath' => 'pages/a']]],
            'miniprogram url 最长 500' => [[['name' => '菜单', 'type' => 'miniprogram', 'appid' => 'wxapp', 'pagepath' => 'pages/a', 'url' => str_repeat('u', 501)]]],
            '子级遵循叶子规则' => [[['name' => '父级', 'sub_button' => [['name' => '子级', 'type' => 'click']]]]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidMenus')]
    public function test_rejects_invalid_nested_menu_structure(array $button): void
    {
        $this->assertInvalid($button);
    }

    public function test_parent_with_children_does_not_require_leaf_fields_and_payload_is_whitelisted(): void
    {
        $this->cacheToken();
        $service = $this->service([self::wechatJson(['errcode' => 0, 'errmsg' => 'ok'])]);

        $service->createMenu([[
            'name' => '父级',
            'extra_evil' => 'drop-me',
            'sub_button' => [[
                'name' => '子级',
                'type' => 'click',
                'key' => 'child',
                'extra_evil' => 'drop-me-too',
            ]],
        ]]);

        $body = json_decode((string) $this->wechatRequests()[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['button' => [[
            'name' => '父级',
            'sub_button' => [['name' => '子级', 'type' => 'click', 'key' => 'child']],
        ]]], $body);
    }

    public function test_get_menu_returns_api_payload_unchanged(): void
    {
        $this->cacheToken();
        $payload = ['is_menu_open' => 1, 'selfmenu_info' => ['button' => [['name' => '首页']]]];

        $this->assertSame($payload, $this->service([self::wechatJson($payload)])->getMenu());
    }

    public function test_translates_wechat_exceptions_to_business_errors(): void
    {
        try {
            $this->service([], false)->getMenu();
            $this->fail('未配置必须抛业务异常');
        } catch (BusinessException $e) {
            $this->assertSame(lang('wechat.official_not_configured'), $e->getMessage());
        }

        $this->cacheToken();
        try {
            $this->service([self::wechatJson(['errcode' => 40018, 'errmsg' => 'invalid button size'])])
                ->createMenu([['name' => '首页', 'type' => 'view', 'url' => 'https://x.test']]);
            $this->fail('微信业务错误必须抛业务异常');
        } catch (BusinessException $e) {
            $this->assertSame(lang('wechat.api_error', ['errmsg' => 'invalid button size']), $e->getMessage());
            $this->assertStringNotContainsString('access_token', $e->getMessage());
        }

        try {
            $this->service([new ConnectException('network with secret URL', new Request('GET', 'https://api.weixin.qq.com'))])->getMenu();
            $this->fail('微信不可用必须抛业务异常');
        } catch (BusinessException $e) {
            $this->assertSame(lang('wechat.unavailable'), $e->getMessage());
        }
    }
}
