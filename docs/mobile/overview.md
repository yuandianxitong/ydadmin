# 移动端概览

## 技术栈

- **UniApp** — 跨端应用框架（Vue 3）
- **Vue 3 Composition API** — `<script setup lang="ts">`
- **Wot Design Uni** — 移动端 UI 组件库（`wd-*` 组件）
- **TypeScript** — 类型安全
- **Pinia** — 状态管理
- **pnpm** — 包管理器

## 支持平台

- 微信小程序
- H5
- App（Android / iOS）
- HarmonyOS

## 项目结构

```
uniapp/
├── src/
│   ├── api/              # API 接口封装
│   │   ├── auth.ts       # 认证
│   │   ├── user.ts       # 用户
│   │   ├── announcement.ts # 公告
│   │   ├── agreement.ts  # 协议
│   │   ├── version.ts    # 版本检查
│   │   └── payment.ts    # 支付
│   ├── components/       # 自定义组件（d-* 前缀）
│   │   ├── d-agreement-check/  # 协议勾选
│   │   ├── d-avatar-upload/    # 头像上传
│   │   ├── d-empty/            # 空状态
│   │   ├── d-list-loader/      # 列表加载器
│   │   ├── d-page/             # 页面容器
│   │   ├── d-payment-popup/    # 支付弹窗
│   │   └── d-price/            # 价格展示
│   ├── hooks/            # 全局 Composables
│   │   ├── useAuth.ts    # 登录状态检查
│   │   ├── usePaging.ts  # 分页加载
│   │   ├── useShare.ts   # 分享
│   │   ├── useUpload.ts  # 文件上传
│   │   └── useVersionCheck.ts # 版本更新检查
│   ├── modules/          # 功能模块（分包）
│   │   ├── login/        # 登录/注册
│   │   ├── user/         # 个人中心
│   │   ├── announcement/ # 公告
│   │   ├── agreement/    # 协议
│   │   ├── feedback/     # 意见反馈
│   │   ├── message/      # 消息中心
│   │   └── payment/      # 支付
│   ├── pages/            # 主包页面
│   ├── store/            # Pinia 状态管理
│   ├── styles/           # 全局样式
│   ├── types/            # TypeScript 类型
│   ├── utils/            # 工具函数
│   └── pages.json        # 路由配置
├── vite.config.ts
└── package.json
```

## 开发命令

```bash
cd uniapp
pnpm install                    # 安装依赖
pnpm dev:mp-weixin             # 微信小程序开发
pnpm dev:h5                    # H5 开发
pnpm build:mp-weixin           # 构建微信小程序
pnpm build:h5                  # 构建 H5
```

## 组件自动注册

通过 `easycom` 配置自动注册组件，无需手动 import：

```json
{
  "easycom": {
    "custom": {
      "^wd-(.*)": "wot-design-uni/components/wd-$1/wd-$1.vue",
      "^d-(.*)": "@/components/d-$1/d-$1.vue"
    }
  }
}
```

- `wd-button`、`wd-input` 等 → Wot Design Uni 组件
- `d-page`、`d-empty` 等 → 自定义业务组件

## 内置 Composables

### useVersionCheck — 版本更新检查

```typescript
import { useVersionCheck } from '@/hooks/useVersionCheck'

const { checking, checkUpdate } = useVersionCheck()

// 静默检查（启动时）
checkUpdate(true)

// 手动检查（用户点击）
checkUpdate(false)
```

自动检测平台、获取当前版本号、调用后端接口、弹出更新对话框，支持强制更新。

### usePaging — 分页加载

```typescript
import { usePaging } from '@/hooks/usePaging'

const { list, loading, finished, getList, refresh } = usePaging({
  fetchFun: (params) => announcementApi.getList(params),
})
```

封装了无限滚动加载、下拉刷新、分页状态管理。

### useAuth — 登录状态

```typescript
import { useAuth } from '@/hooks/useAuth'

const { checkLogin } = useAuth()

// 需要登录才能操作
if (!checkLogin()) return
```

## 页面开发规范

- 使用 `<view>` 代替 `<div>`，`<text>` 代替 `<span>`
- 使用 `@tap` 代替 `@click`
- 使用 `rpx` 作为尺寸单位（750rpx = 屏幕宽度）
- 导航：`uni.navigateTo()`、`uni.navigateBack()`、`uni.reLaunch()`
- 提示：`uni.showToast()`、`uni.showModal()`
- 存储：`uni.getStorageSync()` / `uni.setStorageSync()`

## 内置功能模块

| 模块 | 路径 | 功能 |
|------|------|------|
| 登录注册 | `/modules/login/` | 密码登录、短信登录、注册 |
| 个人中心 | `/modules/user/` | 资料编辑、设置、修改密码 |
| 公告 | `/modules/announcement/` | 公告列表、公告详情 |
| 协议 | `/modules/agreement/` | 用户协议、隐私政策展示 |
| 意见反馈 | `/modules/feedback/` | 提交反馈、图片上传 |
| 消息中心 | `/modules/message/` | 消息列表、消息详情 |
| 支付 | `/modules/payment/` | 微信支付、支付宝支付 |
