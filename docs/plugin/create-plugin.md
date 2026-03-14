# 创建插件

本指南将带你从零开始创建一个 Dev007 插件，涵盖目录结构搭建、生命周期脚本编写、路由注册、事件监听和数据库迁移。

## 使用代码生成器

Dev007 的代码生成器支持 `--as-plugin` 模式，可以快速生成插件的 CRUD 代码骨架：

```bash
cd server
php think make:crud --as-plugin my-plugin --table my_table
```

该命令会在 `plugins/my-plugin/` 目录下自动生成：

- `plugin.json` — 插件配置文件
- `Plugin.php` — 插件主类
- `install.php` — 安装脚本
- `uninstall.php` — 卸载脚本
- `backend/controller/v1/` — 控制器
- `backend/service/` — 服务层
- `backend/repository/` — 数据仓库层
- `backend/model/` — 模型
- `backend/config/routes.php` — 路由文件
- `backend/migration/` — 数据库迁移文件

生成的代码严格遵循 `Controller -> Service -> Repository -> Model` 分层架构。

## 手动创建插件结构

如果不使用代码生成器，你也可以手动创建插件。

### 第一步：创建目录

```bash
mkdir -p plugins/my-plugin/backend/{controller/v1,service,repository,model,listener,config,migration}
```

### 第二步：创建 plugin.json

```json
{
  "title": "我的插件",
  "name": "my-plugin",
  "description": "插件功能描述",
  "version": "1.0.0",
  "author": "Your Name",
  "require": {},
  "hooks": []
}
```

### 第三步：创建插件主类

插件主类必须命名为 `Plugin.php`，放在插件根目录，继承 `core\plugin\BasePlugin`：

```php
<?php
declare(strict_types=1);

namespace plugins\my_plugin;

use core\plugin\BasePlugin;

class Plugin extends BasePlugin
{
    /**
     * 安装插件
     */
    public function install(): bool
    {
        // 调用父类方法（会自动加载 install.php）
        return parent::install();
    }

    /**
     * 卸载插件
     */
    public function uninstall(): bool
    {
        return parent::uninstall();
    }

    /**
     * 启用插件
     */
    public function enable(): bool
    {
        // 在这里执行启用时的初始化逻辑
        return true;
    }

    /**
     * 禁用插件
     */
    public function disable(): bool
    {
        // 在这里执行禁用时的清理逻辑
        return true;
    }

    /**
     * 升级插件
     */
    public function upgrade(string $version): bool
    {
        // 根据版本号执行升级逻辑
        return true;
    }
}
```

::: tip 命名空间约定
插件目录名中的连字符 `-` 在命名空间中需要转换为下划线 `_`。例如：目录 `my-plugin` 对应命名空间 `plugins\my_plugin`。
:::

## 编写 install.php / uninstall.php

### install.php

安装脚本在插件首次安装时执行，适用于初始化数据：

```php
<?php
declare(strict_types=1);

use think\facade\Db;

// 写入默认配置
Db::table('system_configs')->insertAll([
    [
        'group' => 'my_plugin',
        'key' => 'my_plugin_enabled',
        'value' => '1',
        'type' => 'boolean',
        'title' => '启用我的插件功能',
        'created_at' => date('Y-m-d H:i:s'),
    ],
    [
        'group' => 'my_plugin',
        'key' => 'my_plugin_api_key',
        'value' => '',
        'type' => 'string',
        'title' => 'API Key',
        'created_at' => date('Y-m-d H:i:s'),
    ],
]);
```

### uninstall.php

卸载脚本在插件卸载时执行，用于清理安装脚本创建的数据：

```php
<?php
declare(strict_types=1);

use think\facade\Db;

// 删除插件配置
Db::table('system_configs')
    ->where('group', 'my_plugin')
    ->delete();
```

::: warning
数据库表的创建和删除应通过迁移文件管理，而不是写在 `install.php` / `uninstall.php` 中。安装脚本适用于初始化数据，卸载脚本适用于清理数据。
:::

## 注册路由

在 `backend/config/routes.php` 中定义插件的 API 路由：

```php
<?php
declare(strict_types=1);

use think\facade\Route;

// 插件后台管理路由
Route::group('adminapi/plugin/my-plugin', function () {
    Route::get('list', 'plugins\my_plugin\controller\v1\MyController@index');
    Route::get('detail/:id', 'plugins\my_plugin\controller\v1\MyController@detail');
    Route::post('create', 'plugins\my_plugin\controller\v1\MyController@create');
    Route::put('update/:id', 'plugins\my_plugin\controller\v1\MyController@update');
    Route::delete('delete/:id', 'plugins\my_plugin\controller\v1\MyController@delete');
})->middleware([
    \app\adminapi\middleware\AdminAuthMiddleware::class,
    \app\adminapi\middleware\AdminPermissionMiddleware::class,
]);

// 插件前台 API 路由
Route::group('api/plugin/my-plugin', function () {
    Route::get('list', 'plugins\my_plugin\controller\v1\ApiController@list');
})->middleware([
    \app\api\middleware\UserAuthMiddleware::class,
]);
```

插件启用后，`PluginManager::loadRoutes()` 会自动加载这个路由文件。

## 注册事件钩子

### 方式一：在 plugin.json 中声明

在 `plugin.json` 的 `hooks` 字段中注册：

```json
{
  "hooks": [
    {
      "name": "user.register",
      "callback": "plugins\\my_plugin\\listener\\UserRegisterHandler"
    },
    {
      "name": "payment.success",
      "callback": "plugins\\my_plugin\\listener\\PaymentSuccessHandler"
    }
  ]
}
```

### 方式二：在 Plugin 类中注册

如果需要更灵活的控制，可以在插件的 `enable()` 方法中手动注册：

```php
public function enable(): bool
{
    \think\facade\Event::listen(
        'order.created',
        \plugins\my_plugin\listener\OrderCreatedListener::class
    );

    return true;
}
```

### 编写事件监听器

```php
<?php
declare(strict_types=1);

namespace plugins\my_plugin\listener;

use think\facade\Log;

class UserRegisterHandler
{
    public function handle(array $data): void
    {
        $userId = $data['user_id'] ?? null;

        if ($userId) {
            Log::info('【我的插件】新用户注册', ['user_id' => $userId]);
            // 执行插件的业务逻辑...
        }
    }
}
```

## 数据库迁移

### 创建迁移文件

迁移文件存放在 `backend/migration/` 目录，文件名格式为 `{时间戳}_{描述}.php`：

```
backend/migration/
├── 20260314010000_create_my_records_table.php
└── 20260314020000_add_status_to_my_records.php
```

### 迁移文件示例

```php
<?php
declare(strict_types=1);

use think\facade\Db;

class CreateMyRecordsTable
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        Db::execute("
            CREATE TABLE IF NOT EXISTS `my_records` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `title` varchar(255) NOT NULL DEFAULT '' COMMENT '标题',
                `content` text COMMENT '内容',
                `user_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '用户ID',
                `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态: 1启用 0禁用',
                `created_at` datetime DEFAULT NULL COMMENT '创建时间',
                `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
                `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
                PRIMARY KEY (`id`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='我的记录表';
        ");
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Db::execute("DROP TABLE IF EXISTS `my_records`");
    }
}
```

### 迁移执行规则

- **安装时**：按文件名**升序**执行所有迁移文件的 `up()` 方法
- **卸载时**：按文件名**降序**执行所有迁移文件的 `down()` 方法
- 文件名中的时间戳前缀（如 `20260314010000_`）会被去掉，剩余部分转为 PascalCase 作为类名
  - `20260314010000_create_my_records_table.php` -> `CreateMyRecordsTable`

### 编写 Model

```php
<?php
declare(strict_types=1);

namespace plugins\my_plugin\model;

use core\base\Model;

class MyRecord extends Model
{
    protected $table = 'my_records';

    protected $schema = [
        'id'         => 'int',
        'title'      => 'string',
        'content'    => 'string',
        'user_id'    => 'int',
        'status'     => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
```

## 完整插件示例

以下是一个完整的"积分记录"插件结构：

```
plugins/
└── points-log/
    ├── plugin.json
    ├── Plugin.php
    ├── install.php
    ├── uninstall.php
    └── backend/
        ├── config/
        │   └── routes.php
        ├── controller/
        │   └── v1/
        │       └── PointsLogController.php
        ├── service/
        │   └── PointsLogService.php
        ├── repository/
        │   └── PointsLogRepository.php
        ├── model/
        │   └── PointsLog.php
        ├── listener/
        │   └── UserPointsChangedListener.php
        └── migration/
            └── 20260314010000_create_points_log_table.php
```

`plugin.json`：

```json
{
  "title": "积分记录",
  "name": "points-log",
  "description": "记录用户积分变动日志",
  "version": "1.0.0",
  "author": "Dev007",
  "require": {},
  "hooks": [
    {
      "name": "user.points.changed",
      "callback": "plugins\\points_log\\listener\\UserPointsChangedListener"
    }
  ]
}
```

## 下一步

- [插件 API](./plugin-api.md) — 查看 BasePlugin 和 PluginManager 的完整 API 参考
