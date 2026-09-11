<?php

declare(strict_types=1);

namespace tests\Unit\Core;

use core\base\Service;
use DI\Attribute\Inject;
use Illuminate\Database\Schema\Blueprint;
use support\Container;
use support\Db;
use tests\TestCase;

class InnerDemoService extends Service
{
    public function hello(): string
    {
        return 'inner';
    }
}

class OuterDemoService extends Service
{
    #[Inject]
    protected InnerDemoService $inner;

    public function callInner(): string
    {
        return $this->inner->hello();
    }

    public function writeInTx(bool $fail): void
    {
        $this->runInTransaction(function () use ($fail) {
            Db::table('test_tx_items')->insert(['name' => 'x']);
            if ($fail) {
                throw new \RuntimeException('rollback me');
            }
        });
    }
}

final class ServiceDiTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        Db::schema()->dropIfExists('test_tx_items');
        Db::schema()->create('test_tx_items', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });
    }

    public static function tearDownAfterClass(): void
    {
        Db::schema()->dropIfExists('test_tx_items');
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Db::table('test_tx_items')->truncate();
    }

    public function test_inject_attribute_resolves_dependency(): void
    {
        $this->assertSame('inner', Container::get(OuterDemoService::class)->callInner());
    }

    public function test_services_are_container_singletons(): void
    {
        $this->assertSame(Container::get(OuterDemoService::class), Container::get(OuterDemoService::class));
    }

    public function test_transaction_commits(): void
    {
        Container::get(OuterDemoService::class)->writeInTx(false);
        $this->assertSame(1, Db::table('test_tx_items')->count());
    }

    public function test_transaction_rolls_back_on_exception(): void
    {
        try {
            Container::get(OuterDemoService::class)->writeInTx(true);
        } catch (\RuntimeException) {
        }
        $this->assertSame(0, Db::table('test_tx_items')->count());
    }
}
