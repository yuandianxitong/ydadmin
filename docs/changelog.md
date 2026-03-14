# 更新日志

## v1.0.0 (2026-03-14)

### 新功能

- 完整的 RBAC 权限管理系统
- 代码生成器（CLI + Web 界面）
- 插件系统（BasePlugin + CLI 管理命令）
- 内置模块：公告管理、地区数据、协议管理、版本管理、数据导入
- UniApp 移动端基座（登录、用户中心、支付、消息、反馈）
- 错误码分类体系
- CI/CD 流水线（GitHub Actions）
- VitePress 文档站

### 架构

- 分层架构：Controller -> Service -> Repository -> Model
- 自动依赖注入（DI）
- 事件驱动的副作用处理
- JWT 认证
- 数据库迁移 + Seeder

### 技术栈

- 后端：ThinkPHP 8.0 + PHP 8.0+
- 前端：Vue 3 + TypeScript + Element Plus
- 移动端：UniApp + Vue 3 + Wot Design Uni
