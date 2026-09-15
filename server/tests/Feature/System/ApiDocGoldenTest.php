<?php

declare(strict_types=1);

namespace tests\Feature\System;

use app\service\system\ApiDocService;
use core\apidoc\OpenApiDocument;
use ReflectionProperty;
use support\Container;
use tests\Support\ApiTestCase;
use tests\Support\GoldenFile;

/**
 * spec §10.4：整份 admin 文档落黄金文件，复用 M2a 的机制（更新模式永远 fail，防呆见 GoldenFile.php）。
 *
 * 确定性（Ruling 8）：AuthController::loginRules() 在运行时读 login_captcha 开关——种子值是
 * '1'（开），黄金文件里 /adminapi/auth/login 的 captcha/captcha_key 因此必须是必填。这条测试
 * 显式把开关钉回种子值并清配置缓存（setConfig() 已经这么做），不依赖其它测试有没有改过它、
 * 也不依赖测试执行顺序——tearDown 会自动把配置恢复原值。
 *
 * 缓存（Ruling 见任务派单）：ApiDocService::$documentCache 是进程内按 type 缓存的私有静态属性，
 * ApiDocServiceTest::test_document_is_cached_per_type_in_process() 会用反射清空它却不恢复，
 * 可能把某次在别的配置状态下 build 出来的文档写回缓存。黄金测试必须自己先清一遍缓存，
 * 保证读到的是本测试钉好 login_captcha 之后新鲜 build 出来的文档，不受执行顺序影响。
 */
final class ApiDocGoldenTest extends ApiTestCase
{
    use GoldenFile;

    public function test_admin_document_matches_golden_file(): void
    {
        self::ensureRoutesLoaded();

        // 钉住 login_captcha 为种子值'1'（开）：setConfig() 写库后立即 forgetCache()，
        // 保证读到的不是其它测试遗留的配置缓存；tearDown 会自动恢复原值并再次清缓存。
        $this->setConfig('login_captcha', '1');

        $cacheProperty = new ReflectionProperty(ApiDocService::class, 'documentCache');
        $cacheProperty->setAccessible(true);
        $cacheProperty->setValue(null, []);

        $document = Container::get(ApiDocService::class)->document('admin');
        $content = OpenApiDocument::toJson($document) . "\n";

        $this->assertGoldenFile('openapi-admin.json', $content, 'generated-apidoc');
    }
}
