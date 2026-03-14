# 移动端快速开始

Dev007 移动端基于 **UniApp + Vue 3 + TypeScript** 构建，UI 组件库采用 [Wot Design Uni](https://wot-design-uni.cn/)，支持编译到微信小程序、H5、App 等多个平台。

## 环境要求

| 工具 | 版本要求 | 说明 |
|------|---------|------|
| Node.js | >= 18 | 推荐使用 LTS 版本 |
| pnpm | >= 8 | 包管理器 |
| HBuilderX | >= 4.0 | 可选，用于可视化开发和真机调试 |
| 微信开发者工具 | 最新稳定版 | 编译微信小程序时需要 |

::: tip
推荐使用 CLI 方式开发。如果使用 HBuilderX，请确保安装了 `uni-app (Vue3)` 编译插件。
:::

## 安装依赖

```bash
cd uniapp
pnpm install
```

## 开发命令

### 常用开发命令

```bash
# H5 开发（浏览器预览）
pnpm dev:h5

# 微信小程序开发
pnpm dev:mp-weixin

# 支付宝小程序开发
pnpm dev:mp-alipay

# App 开发（需要 HBuilderX）
pnpm dev:custom
```

### 构建命令

```bash
# 构建 H5
pnpm build:h5

# 构建微信小程序
pnpm build:mp-weixin

# 构建支付宝小程序
pnpm build:mp-alipay

# TypeScript 类型检查
pnpm type-check
```

### 微信小程序调试

1. 执行 `pnpm dev:mp-weixin`，编译产物输出到 `dist/dev/mp-weixin/`
2. 打开微信开发者工具，导入该目录
3. 修改源码后自动热更新

## 环境变量配置

在 `uniapp/` 根目录创建 `.env` 文件：

```env
# API 基础地址
VITE_APP_API_URL=http://localhost:8000
```

请求模块会自动读取该变量拼接到所有 API 请求的 URL 前：

```ts
const BASE_URL = import.meta.env.VITE_APP_API_URL || ''
```

## 目录结构

```
uniapp/
├── src/
│   ├── api/                    # API 接口定义
│   │   ├── auth.ts             # 认证相关 API
│   │   ├── payment.ts          # 支付 API
│   │   ├── user.ts             # 用户 API
│   │   ├── message.ts          # 消息 API
│   │   ├── feedback.ts         # 反馈 API
│   │   ├── announcement.ts     # 公告 API
│   │   └── upload.ts           # 上传 API
│   ├── components/             # 自定义组件（easycom 自动注册）
│   │   ├── d-page/             # 页面容器
│   │   ├── d-empty/            # 空状态
│   │   ├── d-list-loader/      # 列表加载器
│   │   ├── d-payment-popup/    # 支付弹窗
│   │   ├── d-pay-result/       # 支付结果
│   │   ├── d-price/            # 价格展示
│   │   ├── d-avatar-upload/    # 头像上传
│   │   ├── d-agreement-check/  # 协议勾选
│   │   ├── d-wechat-login/     # 微信登录
│   │   ├── d-phone-login/      # 手机号登录
│   │   └── d-image-preview/    # 图片预览
│   ├── hooks/                  # Composables
│   │   ├── useAuth.ts          # 登录状态检查
│   │   ├── usePaging.ts        # 分页列表
│   │   ├── useUpload.ts        # 文件上传
│   │   ├── useShare.ts         # 分享
│   │   └── useVersionCheck.ts  # 版本检测
│   ├── modules/                # 分包模块
│   │   ├── login/              # 登录注册
│   │   ├── user/               # 用户中心
│   │   ├── payment/            # 支付
│   │   ├── message/            # 消息中心
│   │   ├── feedback/           # 意见反馈
│   │   ├── announcement/       # 公告
│   │   ├── agreement/          # 协议展示
│   │   └── webview/            # 内嵌网页
│   ├── pages/                  # 主包页面
│   │   └── index/              # 首页
│   ├── store/                  # Pinia 状态管理
│   ├── styles/                 # 全局样式
│   ├── types/                  # TypeScript 类型
│   ├── utils/                  # 工具函数
│   │   ├── request.ts          # 请求封装
│   │   └── auth.ts             # Token 管理
│   ├── App.vue                 # 应用入口组件
│   ├── main.ts                 # 应用入口
│   ├── pages.json              # 路由配置
│   └── manifest.json           # 应用配置
├── package.json
├── tsconfig.json
└── vite.config.ts
```

## pages.json 路由配置

UniApp 使用 `pages.json` 管理页面路由。Dev007 采用**分包加载**架构，主包仅包含首页，业务模块均为分包：

```json
{
  "pages": [
    { "path": "pages/index/index", "style": { "navigationBarTitleText": "首页" } }
  ],
  "subPackages": [
    {
      "root": "modules/login",
      "pages": [
        { "path": "pages/login", "style": { "navigationBarTitleText": "登录", "navigationStyle": "custom" } },
        { "path": "pages/register", "style": { "navigationBarTitleText": "注册" } }
      ]
    },
    {
      "root": "modules/user",
      "pages": [
        { "path": "pages/profile", "style": { "navigationBarTitleText": "个人中心" } },
        { "path": "pages/edit-profile", "style": { "navigationBarTitleText": "编辑资料" } },
        { "path": "pages/change-password", "style": { "navigationBarTitleText": "修改密码" } },
        { "path": "pages/settings", "style": { "navigationBarTitleText": "设置" } }
      ]
    }
  ]
}
```

### 分包策略

| 分包 | 路径 | 包含页面 |
|------|------|---------|
| 登录 | `modules/login` | 登录、注册 |
| 用户 | `modules/user` | 个人中心、编辑资料、修改密码、设置 |
| 支付 | `modules/payment` | 支付结果 |
| 消息 | `modules/message` | 消息列表、消息详情 |
| 反馈 | `modules/feedback` | 意见反馈 |
| 公告 | `modules/announcement` | 公告列表、公告详情 |
| 协议 | `modules/agreement` | 协议展示 |
| 网页 | `modules/webview` | 内嵌网页 |

### easycom 自动注册

`pages.json` 中配置了 easycom 规则，自定义组件和 Wot Design 组件无需手动导入即可直接使用：

```json
{
  "easycom": {
    "autoscan": true,
    "custom": {
      "^wd-(.*)": "wot-design-uni/components/wd-$1/wd-$1.vue",
      "^d-(.*)": "@/components/d-$1/d-$1.vue"
    }
  }
}
```

- 以 `wd-` 开头的标签自动映射到 Wot Design Uni 组件
- 以 `d-` 开头的标签自动映射到项目自定义组件

## 请求封装

移动端的 HTTP 请求统一封装在 `src/utils/request.ts` 中，基于 `uni.request` 实现：

- 自动携带 JWT Token（`Authorization: Bearer xxx`）
- 支持 GET / POST / PUT / DELETE
- 自动处理 loading 状态
- 401 响应自动跳转登录页并清除 Token
- 统一错误提示

```ts
import http from '@/utils/request'

// GET 请求
const data = await http.get<UserInfo>('/api/auth/info')

// POST 请求
const result = await http.post<LoginResult>('/api/auth/login', {
  mobile: '13800138000',
  password: '123456',
})
```

## 下一步

- [内置模块](./modules.md) - 了解登录、支付等功能模块
- [自定义组件](./components.md) - 查看所有可用组件
- [支付集成](./payment.md) - 接入微信支付和支付宝
- [微信登录](./wechat-login.md) - 微信小程序登录对接
