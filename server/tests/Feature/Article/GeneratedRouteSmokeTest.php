<?php

declare(strict_types=1);

namespace tests\Feature\Article;

use app\repository\agreement\AgreementRepository;
use app\repository\announcement\AnnouncementRepository;
use app\repository\article\ArticleCategoryRepository;
use app\repository\article\ArticleRepository;
use ReflectionProperty;
use tests\Support\ApiTestCase;

final class GeneratedRouteSmokeTest extends ApiTestCase
{
    public function test_admin_list_paths_require_auth_and_return_empty_page(): void
    {
        foreach (['/adminapi/article/list', '/adminapi/announcement/list', '/adminapi/agreement/list'] as $path) {
            $this->get($path)->assertCode(401);
            $admin = $this->actingAsAdmin('super');
            $data = $this->get($path, ['page' => 1, 'limit' => 10], $admin->token)->assertOk()->data();
            $this->assertSame(['list', 'pagination'], array_keys($data));
        }
        $this->get('/adminapi/article-category/list')->assertCode(401);
        $admin = $this->actingAsAdmin('super');
        $tree = $this->get('/adminapi/article-category/list', [], $admin->token)->assertOk()->data();
        $this->assertIsArray($tree);
        $this->assertArrayNotHasKey('pagination', is_array($tree) ? $tree : []);
    }

    public function test_article_and_announcement_repositories_are_not_data_scoped(): void
    {
        foreach ([ArticleRepository::class, AnnouncementRepository::class] as $class) {
            $p = new ReflectionProperty($class, 'dataScoped');
            $this->assertFalse($p->getDefaultValue(), $class);
        }
        foreach ([ArticleCategoryRepository::class, AgreementRepository::class] as $class) {
            $p = new ReflectionProperty($class, 'dataScoped');
            $this->assertSame(\core\base\Repository::class, $p->getDeclaringClass()->getName());
        }
    }
}
