<?php

declare(strict_types=1);

namespace app\bootstrap;

use Illuminate\Container\Container as IlluminateContainer;
use Illuminate\Database\DatabaseTransactionsManager;
use Webman\Bootstrap;
use Webman\Database\Initializer;
use Workerman\Worker;

/**
 * 显式初始化 Eloquent，并为每个连接装配 afterCommit 需要的事务管理器。
 *
 * webman/database 只在 support\Db / support\Model 类文件被 require 时顺带调用
 * Initializer::init()；core\base\Model 直接继承 Eloquent Model、不经过这两个类，
 * 某些代码路径会报 "Call to a member function connection() on null"。这里在每个
 * worker / 命令进程启动时显式初始化一次（Initializer 自带幂等锁，重复调用安全）。
 */
class Database implements Bootstrap
{
    public static function start(?Worker $worker): void
    {
        Initializer::init(config('database', []));

        // 连接池每新建一个物理连接都会经过 DatabaseManager::configure()，其中若容器绑定了
        // 'db.transactions' 就为该连接注入事务管理器。用 bind 而非 singleton：每个连接一个
        // 独立实例，避免多个连接共享事务栈而串扰 afterCommit 回调。
        $container = IlluminateContainer::getInstance();
        if (!$container->bound('db.transactions')) {
            $container->bind('db.transactions', static fn () => new DatabaseTransactionsManager());
        }
    }
}
