# 项目介绍

## 什么是 Dev007 Framework

Dev007 Framework 是一个开源通用管理后台框架，提供完整的后台管理系统、移动端应用和插件生态。基于 ThinkPHP 8 + Vue 3 + UniApp 技术栈，帮助开发者快速搭建专属管理系统。

## 核心功能

- **RBAC 权限管理** — 基于角色的访问控制，支持菜单权限、按钮权限、数据权限
- **代码生成器** — 通过命令行 `php think make:crud` 或管理后台页面，一键生成 CRUD 全套代码（Model、Repository、Service、Controller、Validate、Route、前端 API 及页面组件）
- **插件系统** — 可插拔功能模块，按需组合，支持快速扩展业务
- **支付集成** — 内置支付模块，支持多种支付方式，通过事件系统处理支付回调
- **消息中心** — 站内消息、通知推送等消息管理功能
- **文件管理** — 统一的文件上传、存储与管理方案
- **系统配置** — 灵活的系统配置管理，支持分组、多类型配置项

## 技术栈

| 端 | 技术栈 |
|---|---|
| 后端 | ThinkPHP 8.0 + PHP 8.0+ + MySQL 5.7+ |
| 管理后台 | Vue 3 + TypeScript + Element Plus + Vite + Pinia |
| 移动端 | UniApp + Vue 3 + Wot Design Uni |

## 架构设计

项目后端采用严格的分层架构，保证代码清晰、可维护：

```
请求 → Controller → Service → Repository → Model
                       │
                       ├── Listener（事件监听器，处理副作用）
                       └── Job（异步队列任务）
```

各层职责：

- **Controller** — 接收请求、参数校验、调用 Service、返回响应
- **Service** — 业务逻辑编排、事务管理、触发事件
- **Repository** — 数据访问封装、所有 ORM 查询集中于此
- **Model** — ORM 映射、关联关系、访问器/修改器
- **Listener** — 事件监听器，处理日志、通知、缓存清理等副作用
- **Job** — 异步队列任务，处理耗时操作

详细的架构说明请参考 [后端架构设计](/backend/architecture)。

## 项目结构

```
framework/
├── admin/          # 管理后台前端（Vue 3 + Element Plus）
├── server/         # 后端服务（ThinkPHP 8）
├── uniapp/         # 移动端应用（UniApp + Vue 3）
├── docs/           # 项目文档（VitePress）
├── docker/         # Docker 配置
├── docker-compose.yml
├── deploy.sh       # 部署脚本
└── setup.sh        # 初始化脚本
```

## 适用场景

Dev007 Framework 适合以下场景的开发者：

- 需要快速搭建管理后台的中小企业
- 希望同时具备 Web 后台和移动端的项目
- 需要完整 RBAC 权限体系的 SaaS 应用
- 需要代码生成器提高开发效率的团队
- 需要插件化架构支持业务扩展的产品
