# 插件系统介绍

Dev007 提供了一套灵活的插件系统，允许开发者在不修改核心代码的情况下扩展系统功能。插件支持独立的路由、事件钩子、数据库迁移和配置管理。

## 插件系统概述

插件系统由以下核心组件构成：

| 组件 | 类 | 说明 |
|------|------|------|
| 插件基类 | `core\plugin\BasePlugin` | 所有插件的基类，提供生命周期钩子和工具方法 |
| 插件管理器 | `core\plugin\PluginManager` | 管理插件的加载、安装、启用、禁用和卸载 |
| 插件数据表 | `plugins` | 存储已安装插件的元信息和状态 |

### 架构设计

```
PluginManager::init()
    ├── loadPlugins()         ← 从数据库加载已启用插件列表（带缓存）
    ├── registerAutoload()    ← 注册插件的 PSR-4 自动加载
    ├── registerHooks()       ← 注册插件定义的事件钩子
    └── loadRoutes()          ← 加载插件路由文件
```

## 插件目录结构

所有插件存放在项目根目录的 `plugins/` 下，每个插件一个独立目录：

```
plugins/
└── my-plugin/
    ├── plugin.json                 # 插件配置文件（必需）
    ├── Plugin.php                  # 插件主类（必需，继承 BasePlugin）
    ├── install.php                 # 安装脚本（可选）
    ├── uninstall.php               # 卸载脚本（可选）
    └── backend/
        ├── config/
        │   └── routes.php          # 插件路由定义
        ├── controller/
        │   └── v1/                 # 控制器
        ├── service/                # 服务层
        ├── repository/             # 数据仓库层
        ├── model/                  # 模型
        ├── listener/               # 事件监听器
        └── migration/              # 数据库迁移文件
            ├── 20260314010000_create_xxx_table.php
            └── 20260314020000_add_xxx_column.php
```

### 命名空间

插件代码使用 `plugins\{插件名}\` 命名空间，例如：

```php
namespace plugins\my_plugin\controller\v1;

class MyController extends \core\base\Controller
{
    // ...
}
```

插件管理器在初始化时自动注册 PSR-4 自动加载规则，将 `plugins\my_plugin\` 前缀映射到 `plugins/my-plugin/backend/` 目录。

## plugin.json 配置格式

每个插件必须在根目录包含一个 `plugin.json` 文件，描述插件的基本信息和配置：

```json
{
  "title": "示例插件",
  "name": "example-plugin",
  "description": "这是一个示例插件，演示插件系统的基本功能",
  "version": "1.0.0",
  "author": "Dev007",
  "require": {
    "another-plugin": ">=1.0.0"
  },
  "hooks": [
    {
      "name": "user.register",
      "callback": "plugins\\example_plugin\\listener\\UserRegisterListener"
    }
  ]
}
```

### 字段说明

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| title | `string` | 是 | 插件显示名称 |
| name | `string` | 是 | 插件唯一标识（目录名） |
| description | `string` | 否 | 插件描述 |
| version | `string` | 是 | 语义化版本号 |
| author | `string` | 否 | 作者 |
| require | `object` | 否 | 依赖的其他插件，`{插件名: 版本约束}` |
| hooks | `array` | 否 | 事件钩子注册列表 |

### 依赖声明

在 `require` 字段中声明插件依赖。安装时 `PluginManager` 会检查所有依赖插件是否已安装：

```json
{
  "require": {
    "payment-plugin": ">=1.0.0",
    "user-plugin": ">=2.0.0"
  }
}
```

如果依赖未满足，安装将被阻止并记录警告日志。

### 钩子注册

在 `hooks` 字段中声明插件需要监听的事件。插件启用后，`PluginManager::registerHooks()` 会自动将这些回调注册到事件系统：

```json
{
  "hooks": [
    {
      "name": "admin.login.success",
      "callback": "plugins\\my_plugin\\listener\\LoginListener"
    },
    {
      "name": "config.changed",
      "callback": "plugins\\my_plugin\\listener\\ConfigListener"
    }
  ]
}
```

## 插件生命周期

插件有四个核心生命周期阶段：

### 安装（Install）

```
PluginManager::install('my-plugin')
    ├── 实例化插件：new Plugin('my-plugin')
    ├── 检查依赖：$plugin->checkDependencies()
    ├── 执行迁移：$plugin->runMigrations()
    ├── 执行安装：$plugin->install()
    │   └── 加载 install.php（如果存在）
    ├── 写入数据库：plugins 表新增记录（status=1）
    └── 清除缓存
```

安装时自动执行 `backend/migration/` 下的所有迁移文件（按文件名升序），创建插件所需的数据库表。

### 启用（Enable）

```
PluginManager::enable('my-plugin')
    ├── 执行启用：$plugin->enable()
    ├── 更新数据库：status → 1
    └── 清除缓存
```

启用后，下次请求时 `PluginManager::init()` 会加载该插件的路由、钩子和自动加载。

### 禁用（Disable）

```
PluginManager::disable('my-plugin')
    ├── 执行禁用：$plugin->disable()
    ├── 更新数据库：status → 0
    └── 清除缓存
```

禁用后，插件的路由和钩子不再生效，但数据库表和数据保留。

### 卸载（Uninstall）

```
PluginManager::uninstall('my-plugin')
    ├── 执行卸载：$plugin->uninstall()
    │   └── 加载 uninstall.php（如果存在）
    ├── 回滚迁移：$plugin->rollbackMigrations()
    ├── 删除数据库记录
    └── 清除缓存
```

卸载时按文件名降序回滚所有迁移，删除插件创建的数据库表。

### 升级（Upgrade）

```
PluginManager::upgrade('my-plugin', '2.0.0')
    ├── 执行升级：$plugin->upgrade('2.0.0')
    ├── 更新数据库版本号
    └── 清除缓存
```

## 插件数据表

已安装的插件信息存储在 `plugins` 表中：

| 字段 | 类型 | 说明 |
|------|------|------|
| name | `string` | 插件名称（唯一标识） |
| title | `string` | 显示名称 |
| version | `string` | 当前版本号 |
| author | `string` | 作者 |
| description | `string` | 描述 |
| status | `int` | 状态：1 启用，0 禁用 |
| installed_at | `datetime` | 安装时间 |
| updated_at | `datetime` | 更新时间 |

## 缓存机制

插件管理器使用 ThinkPHP 缓存系统优化性能：

- 已启用插件列表缓存在 `enabled_plugins` 键中，TTL 3600 秒
- 插件安装、启用、禁用、卸载和升级操作后自动清除缓存
- 使用 `plugin` 缓存标签进行批量清理

## 下一步

- [创建插件](./create-plugin.md) — 学习如何开发自己的插件
- [插件 API](./plugin-api.md) — 查看完整的插件 API 参考
