<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\repository\agreement\AgreementRepository;
use app\repository\announcement\AnnouncementRepository;
use app\repository\article\ArticleCategoryRepository;
use app\repository\article\ArticleRepository;
use app\repository\feedback\FeedbackRepository;
use core\base\Repository;
use ReflectionProperty;
use tests\TestCase;

/**
 * 红线（M7a）：内容模块仓储不受数据权限约束。
 *
 * 文章 / 公告表有 created_by，但对持有 article.* / announcement.* 的管理员是同一份，
 * 允许本类显式声明 `$dataScoped = false`，反射默认值必须是 false。
 *
 * 分类 / 协议 / 反馈没有 created_by 也没有部门列，必须**不重新声明** `$dataScoped`
 *（沿用基类 core\base\Repository 的默认值 false），与 Test22 / Test26 / Test35 同理。
 */
final class Test41_ContentRepositoriesNotDataScopedTest extends TestCase
{
    public function test_article_and_announcement_repositories_data_scoped_default_is_false(): void
    {
        foreach ([ArticleRepository::class, AnnouncementRepository::class] as $class) {
            $property = new ReflectionProperty($class, 'dataScoped');
            $this->assertFalse($property->getDefaultValue(), "{$class} 的 \$dataScoped 默认值必须是 false");
        }
    }

    public function test_category_agreement_feedback_repositories_do_not_redeclare_data_scoped(): void
    {
        foreach ([ArticleCategoryRepository::class, AgreementRepository::class, FeedbackRepository::class] as $class) {
            $this->assertNotRedeclared($class);
        }
    }

    /** @param class-string $class */
    private function assertNotRedeclared(string $class): void
    {
        $property = new ReflectionProperty($class, 'dataScoped');
        $this->assertSame(
            Repository::class,
            $property->getDeclaringClass()->getName(),
            "{$class} 不应重新声明 \$dataScoped：分类/协议/反馈没有 created_by 也没有部门列"
        );
        $this->assertFalse($property->getDefaultValue(), '基类默认值应为 false');
    }
}
