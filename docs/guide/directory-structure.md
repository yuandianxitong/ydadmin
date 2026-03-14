# 目录结构

## 项目总览

```
framework/
├── admin/                 # 管理后台前端
│   ├── src/
│   │   ├── api/           # API 接口封装
│   │   ├── components/    # 公共组件
│   │   ├── directives/    # 自定义指令
│   │   ├── locales/       # 国际化文件
│   │   ├── router/        # 路由配置
│   │   ├── store/modules/ # Pinia 状态管理
│   │   ├── types/         # TypeScript 类型
│   │   ├── utils/         # 工具函数
│   │   └── views/         # 页面组件
│   ├── vite.config.ts
│   └── package.json
│
├── server/                # 后端服务
│   ├── app/
│   │   ├── adminapi/      # 管理后台 API
│   │   │   ├── controller/v1/  # 控制器
│   │   │   ├── middleware/     # 中间件
│   │   │   ├── validate/v1/   # 验证器
│   │   │   └── route/         # 路由定义
│   │   ├── api/           # C 端 API
│   │   │   ├── controller/v1/
│   │   │   ├── middleware/
│   │   │   └── route/
│   │   ├── model/         # 数据模型
│   │   ├── repository/    # 数据仓库
│   │   ├── service/       # 业务逻辑
│   │   ├── listener/      # 事件监听器
│   │   ├── job/           # 队列任务
│   │   ├── command/       # CLI 命令
│   │   └── event.php      # 事件注册
│   ├── core/              # 框架核心
│   │   ├── base/          # 基类（Controller、Service、Repository、Model）
│   │   ├── exception/     # 异常处理
│   │   ├── plugin/        # 插件系统
│   │   └── utils/         # 核心工具
│   ├── database/
│   │   ├── migrations/    # 数据库迁移
│   │   └── seeds/         # 数据填充
│   ├── tests/             # 测试
│   └── composer.json
│
├── uniapp/                # 移动端应用
│   ├── src/
│   │   ├── api/           # API 请求
│   │   ├── components/    # 自定义组件（d-* 前缀）
│   │   ├── hooks/         # 组合式函数
│   │   ├── modules/       # 功能模块（分包）
│   │   ├── pages/         # 主包页面
│   │   ├── store/         # 状态管理
│   │   ├── styles/        # 全局样式
│   │   ├── types/         # 类型定义
│   │   ├── utils/         # 工具函数
│   │   └── pages.json     # 路由配置
│   └── package.json
│
├── docs/                  # 项目文档（VitePress）
├── docker/                # Docker 配置
├── plugins/               # 插件目录
├── .github/workflows/     # CI/CD
├── setup.sh               # 初始化脚本
├── deploy.sh              # 部署脚本
└── docker-compose.yml
```

## 后端分层目录

后端严格遵循 `Controller → Service → Repository → Model` 分层架构，每个业务模块的文件分布如下：

| 文件类型 | 路径 | 说明 |
|---------|------|------|
| Controller（Admin） | `app/adminapi/controller/v1/{module}/` | 管理端接口 |
| Controller（C 端） | `app/api/controller/v1/{module}/` | C 端接口 |
| Service | `app/service/{module}/` | 业务逻辑 |
| Repository | `app/repository/{module}/` | 数据访问 |
| Model | `app/model/{module}/` | 数据模型 |
| Validate | `app/adminapi/validate/v1/{module}/` | 表单验证 |
| Route（Admin） | `app/adminapi/route/{module}.php` | 管理端路由 |
| Route（C 端） | `app/api/route/{module}.php` | C 端路由 |
| Listener | `app/listener/` | 事件监听器 |

## 前端页面目录

每个 CRUD 模块包含以下文件：

```
admin/src/
├── api/{module}.ts                              # API 接口
├── views/{category}/{module}/index.vue          # 列表页
├── views/{category}/{module}/components/        # 子组件
│   └── {Module}Form.vue                         # 表单弹窗
├── types/api.d.ts                               # 类型定义
└── locales/
    ├── zh-CN.ts                                 # 中文
    └── en-US.ts                                 # 英文
```
