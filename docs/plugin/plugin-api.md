# 插件 API

本文档提供 Dev007 插件系统的完整 API 参考，包括 `BasePlugin` 基类方法、`PluginManager` 管理接口和 CLI 命令。

## BasePlugin 类方法

**命名空间：** `core\plugin\BasePlugin`

所有插件主类必须继承此抽象基类。

### 构造函数

```php
public function __construct(string $name)
```

- 设置插件名称和根目录路径
- 自动加载 `plugin.json` 配置信息
- 插件根目录为 `root_path('plugins') . $name . '/'`

### 信息获取方法

#### getName()

```php
public function getName(): string
```

获取插件名称（即目录名）。

#### getPath()

```php
public function getPath(): string
```

获取插件根目录的绝对路径。

#### getTitle()

```php
public function getTitle(): string
```

获取插件显示标题。如果 `plugin.json` 未定义 `title`，则返回插件名称。

#### getVersion()

```php
public function getVersion(): string
```

获取插件版本号。默认返回 `'1.0.0'`。

#### getAuthor()

```php
public function getAuthor(): string
```

获取插件作者。

#### getDescription()

```php
public function getDescription(): string
```

获取插件描述信息。

#### getInfo()

```php
public function getInfo(): array
```

获取 `plugin.json` 的完整解析内容（关联数组）。

### 依赖检查

#### checkDependencies()

```php
public function checkDependencies(): bool
```

检查 `plugin.json` 中 `require` 字段声明的依赖是否满足。遍历所有依赖项，调用 `PluginManager::isInstalled()` 确认依赖插件已安装。

- 无依赖或所有依赖满足时返回 `true`
- 依赖未满足时记录 warning 日志并返回 `false`

### 数据库迁移

#### runMigrations()

```php
public function runMigrations(): void
```

按文件名升序执行 `backend/migration/` 目录下的所有迁移文件的 `up()` 方法。如果迁移目录不存在，则静默返回。

#### rollbackMigrations()

```php
public function rollbackMigrations(): void
```

按文件名**降序**执行所有迁移文件的 `down()` 方法，用于卸载时回滚数据库变更。

### 生命周期钩子

子类可覆盖以下方法实现自定义逻辑。所有方法返回 `bool`，表示操作是否成功。

#### install()

```php
public function install(): bool
```

安装插件。默认实现：如果插件目录下存在 `install.php`，自动加载执行。返回 `true`。

#### uninstall()

```php
public function uninstall(): bool
```

卸载插件。默认实现：如果插件目录下存在 `uninstall.php`，自动加载执行。返回 `true`。

#### enable()

```php
public function enable(): bool
```

启用插件。默认返回 `true`。子类可覆盖以执行启用时的初始化操作。

#### disable()

```php
public function disable(): bool
```

禁用插件。默认返回 `true`。子类可覆盖以执行禁用时的清理操作。

#### upgrade(string $version)

```php
public function upgrade(string $version): bool
```

升级插件到指定版本。默认返回 `true`。子类可根据 `$version` 参数执行版本特定的升级逻辑。

## PluginManager API

**命名空间：** `core\plugin\PluginManager`

插件管理器，所有方法均为静态方法。

### 初始化

#### init()

```php
public static function init(): void
```

初始化插件系统。该方法幂等，重复调用不会重复加载。初始化流程：

1. `loadPlugins()` — 从数据库加载已启用插件列表（缓存 1 小时）
2. `registerAutoload()` — 为每个已启用插件注册 `spl_autoload_register`
3. `registerHooks()` — 注册 `plugin.json` 中定义的事件钩子
4. `loadRoutes()` — 加载各插件的 `backend/config/routes.php`

### 插件管理

#### install(string $name)

```php
public static function install(string $name): bool
```

安装指定名称的插件。流程：
1. 获取插件实例
2. 检查依赖（`checkDependencies()`）
3. 执行数据库迁移（`runMigrations()`）
4. 调用插件的 `install()` 方法
5. 在 `plugins` 表中插入记录（默认启用 `status=1`）
6. 清除缓存

失败时捕获异常，记录错误日志，返回 `false`。

#### uninstall(string $name)

```php
public static function uninstall(string $name): bool
```

卸载指定插件。流程：
1. 调用插件的 `uninstall()` 方法
2. 回滚数据库迁移（`rollbackMigrations()`）
3. 从 `plugins` 表删除记录
4. 清除缓存

#### enable(string $name)

```php
public static function enable(string $name): bool
```

启用已禁用的插件。更新 `plugins` 表中 `status` 为 `1`。

#### disable(string $name)

```php
public static function disable(string $name): bool
```

禁用已启用的插件。更新 `plugins` 表中 `status` 为 `0`。

#### upgrade(string $name, string $version)

```php
public static function upgrade(string $name, string $version): bool
```

升级插件到指定版本。调用插件的 `upgrade()` 方法，成功后更新数据库中的版本号和更新时间。

### 查询方法

#### getInstalledPlugins()

```php
public static function getInstalledPlugins(): array
```

获取所有已安装插件列表。返回 `plugins` 表中的所有记录。

#### getEnabledPlugins()

```php
public static function getEnabledPlugins(): array
```

获取所有已启用插件列表。返回 `plugins` 表中 `status=1` 的记录。

#### isInstalled(string $name)

```php
public static function isInstalled(string $name): bool
```

检查指定插件是否已安装。

#### isEnabled(string $name)

```php
public static function isEnabled(string $name): bool
```

检查指定插件是否已启用。

#### scanAvailablePlugins()

```php
public static function scanAvailablePlugins(): array
```

扫描 `plugins/` 目录，返回所有包含 `plugin.json` 的可用插件信息。每个插件附带 `installed` 和 `enabled` 状态标记。

返回值示例：

```php
[
    'my-plugin' => [
        'title' => '我的插件',
        'version' => '1.0.0',
        'author' => 'Dev007',
        'description' => '...',
        'installed' => true,
        'enabled' => true,
    ],
    'another-plugin' => [
        'title' => '另一个插件',
        'version' => '2.0.0',
        'installed' => false,
        'enabled' => false,
    ],
]
```

## CLI 命令

Dev007 提供了一组 Artisan 风格的命令来管理插件。

### plugin:list

列出所有可用和已安装的插件：

```bash
php think plugin:list
```

输出示例：

```
+---------------+-----------+---------+--------+---------+
| Name          | Title     | Version | Status | Author  |
+---------------+-----------+---------+--------+---------+
| my-plugin     | 我的插件   | 1.0.0   | 启用   | Dev007  |
| demo-plugin   | 演示插件   | 1.0.0   | 未安装 | Dev007  |
+---------------+-----------+---------+--------+---------+
```

### plugin:install

安装指定插件：

```bash
php think plugin:install my-plugin
```

### plugin:uninstall

卸载指定插件：

```bash
php think plugin:uninstall my-plugin
```

::: warning
卸载操作会回滚该插件的所有数据库迁移，相关数据将被删除。请确保在卸载前备份重要数据。
:::

### plugin:enable

启用已禁用的插件：

```bash
php think plugin:enable my-plugin
```

### plugin:disable

禁用已启用的插件：

```bash
php think plugin:disable my-plugin
```

## 钩子系统

插件钩子基于 ThinkPHP 的事件系统实现。

### 注册钩子

**方式一：plugin.json 声明（推荐）**

```json
{
  "hooks": [
    {
      "name": "user.register",
      "callback": "plugins\\my_plugin\\listener\\UserRegisterListener"
    }
  ]
}
```

**方式二：代码中动态注册**

```php
use think\facade\Event;

Event::listen('order.paid', function ($data) {
    // 处理订单支付事件
});
```

### 系统内置事件

以下事件可在插件中监听：

| 事件名 | 触发时机 | 数据 |
|--------|---------|------|
| `admin.login.success` | 管理员登录成功 | 管理员信息 |
| `admin.login.failed` | 管理员登录失败 | 登录参数 |
| `config.changed` | 系统配置变更 | 配置信息 |
| `user.register` | 用户注册 | 用户信息 |
| `user.login` | 用户登录 | 用户信息 |
| `payment.success` | 支付成功 | 订单和支付信息 |

### 在 Service 中触发事件

插件的 Service 中可以使用 `$this->trigger()` 触发自定义事件：

```php
class MyService extends \core\base\Service
{
    public function doSomething(array $data): void
    {
        // 业务逻辑...

        // 触发事件
        $this->trigger('my_plugin.action.completed', $data);
    }
}
```

## 插件配置读取

插件可以将自己的配置存储在 `system_configs` 表中，通过系统配置服务读取：

### 写入配置

在 `install.php` 中写入默认配置：

```php
Db::table('system_configs')->insert([
    'group' => 'my_plugin',
    'key' => 'my_plugin_api_url',
    'value' => 'https://api.example.com',
    'type' => 'string',
    'title' => 'API 地址',
    'created_at' => date('Y-m-d H:i:s'),
]);
```

### 读取配置

```php
use app\service\ConfigService;

$configService = app(ConfigService::class);

// 获取单个配置
$apiUrl = $configService->get('my_plugin_api_url');

// 获取整组配置
$pluginConfig = $configService->getGroup('my_plugin');
```

### 在管理后台管理

插件配置会自动出现在管理后台的"系统配置"页面中，按 `group` 分组展示，管理员可以在线修改。修改后会自动触发 `config.changed` 事件清除缓存。
