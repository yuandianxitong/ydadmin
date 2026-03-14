# 内置模块

Dev007 移动端内置了多个常用业务模块，采用 UniApp **分包加载** 架构组织，开箱即用。

## 登录模块

**分包路径：** `modules/login`

### 页面

| 页面 | 路径 | 说明 |
|------|------|------|
| 登录页 | `/modules/login/pages/login` | 支持密码登录、短信登录、微信登录 |
| 注册页 | `/modules/login/pages/register` | 手机号 + 验证码 + 密码注册 |

### 登录方式

#### 密码登录

通过 `authApi.login` 接口，传入手机号和密码：

```ts
const res = await authApi.login({
  mobile: '13800138000',
  password: '123456',
})
```

#### 短信验证码登录

使用 `d-phone-login` 组件，内部自动处理发送验证码和登录流程：

```vue
<d-phone-login @success="onLoginSuccess" @fail="onLoginFail" />
```

相关 API：
- `authApi.sendSmsCode({ mobile })` — 发送验证码
- `authApi.smsLogin({ mobile, code })` — 短信登录

#### 微信小程序登录

使用 `d-wechat-login` 组件，一键调用 `uni.login` 获取 code 并登录：

```vue
<d-wechat-login @success="onLoginSuccess" @fail="onLoginFail" />
```

相关 API：
- `authApi.wechatMiniLogin({ code })` — 微信登录

### 登录状态管理

使用 `useAuth` composable 检查登录状态：

```ts
import { useAuth } from '@/hooks/useAuth'

const { checkLogin, isLoggedIn } = useAuth()

// 需要登录时调用，未登录自动跳转登录页
if (!checkLogin()) return
```

### 协议勾选

登录和注册页面可集成 `d-agreement-check` 组件：

```vue
<d-agreement-check v-model="agreed" />
```

## 用户中心

**分包路径：** `modules/user`

### 页面

| 页面 | 路径 | 说明 |
|------|------|------|
| 个人中心 | `/modules/user/pages/profile` | 用户信息展示、功能入口 |
| 编辑资料 | `/modules/user/pages/edit-profile` | 修改头像、昵称等基本信息 |
| 修改密码 | `/modules/user/pages/change-password` | 原密码 + 新密码修改 |
| 设置 | `/modules/user/pages/settings` | 应用设置、退出登录 |

### 头像上传

编辑资料页面使用 `d-avatar-upload` 组件上传头像：

```vue
<d-avatar-upload v-model="userInfo.avatar" />
```

组件内部调用 `useUpload` composable 完成图片选择和上传，支持限制文件大小（默认 5MB）。

### 相关 API

```ts
import { authApi } from '@/api/auth'
import { userApi } from '@/api/user'

// 获取用户信息
const info = await authApi.getUserInfo()

// 退出登录
await authApi.logout()
```

## 支付模块

**分包路径：** `modules/payment`

### 页面

| 页面 | 路径 | 说明 |
|------|------|------|
| 支付结果 | `/modules/payment/pages/pay-result` | 展示支付成功/失败状态 |

### 支付流程

1. 业务页面展示 `d-payment-popup` 选择支付方式
2. 调用 `usePayment().pay()` 发起支付
3. 跳转到支付结果页面

支持微信支付和支付宝支付两种渠道。

::: tip
详细支付集成文档请参阅 [支付集成](./payment.md)。
:::

## 消息中心

**分包路径：** `modules/message`

### 页面

| 页面 | 路径 | 说明 |
|------|------|------|
| 消息列表 | `/modules/message/pages/message-list` | 展示消息列表，支持下拉刷新和上拉加载 |
| 消息详情 | `/modules/message/pages/message-detail` | 查看消息详细内容 |

### 分页加载

消息列表使用 `usePaging` composable 实现分页：

```ts
import { usePaging } from '@/hooks/usePaging'
import { messageApi } from '@/api/message'

const { list, loading, finished, total, getList, refresh } = usePaging({
  fetchFun: messageApi.getList,
  size: 15,
})
```

配合 `d-list-loader` 组件显示加载状态：

```vue
<view v-for="item in list" :key="item.id">
  <!-- 消息列表项 -->
</view>
<d-list-loader :loading="loading" :finished="finished" :total="total" />
```

### 相关 API

```ts
import { messageApi } from '@/api/message'

// 获取消息列表
const result = await messageApi.getList({ page_no: 1, page_size: 15 })
```

## 意见反馈

**分包路径：** `modules/feedback`

### 页面

| 页面 | 路径 | 说明 |
|------|------|------|
| 意见反馈 | `/modules/feedback/pages/feedback` | 提交意见反馈表单 |

### 功能

- 支持输入反馈内容
- 可上传图片作为附件
- 提交后 Toast 提示

### 相关 API

```ts
import { feedbackApi } from '@/api/feedback'

await feedbackApi.submit({
  content: '反馈内容',
  images: ['image1.jpg', 'image2.jpg'],
})
```

## 公告模块

**分包路径：** `modules/announcement`

### 页面

| 页面 | 路径 | 说明 |
|------|------|------|
| 公告列表 | `/modules/announcement/pages/announcement-list` | 公告列表，支持下拉刷新 |
| 公告详情 | `/modules/announcement/pages/announcement-detail` | 查看公告详细内容 |

### 功能

- 公告列表支持下拉刷新（`enablePullDownRefresh: true`）
- 使用 `usePaging` 分页加载
- 点击列表项跳转到详情页

### 相关 API

```ts
import { announcementApi } from '@/api/announcement'

// 获取公告列表
const result = await announcementApi.getList({ page_no: 1, page_size: 15 })

// 获取公告详情
const detail = await announcementApi.getDetail(id)
```

## 协议展示

**分包路径：** `modules/agreement`

### 页面

| 页面 | 路径 | 说明 |
|------|------|------|
| 协议页面 | `/modules/agreement/pages/agreement` | 展示用户协议、隐私政策等 |

### 使用方式

通过 URL 参数 `code` 指定要展示的协议：

```ts
// 查看用户协议
uni.navigateTo({ url: '/modules/agreement/pages/agreement?code=user_agreement' })

// 查看隐私政策
uni.navigateTo({ url: '/modules/agreement/pages/agreement?code=privacy_policy' })
```

协议内容由后台管理系统配置，前端从接口获取富文本内容进行渲染。

### 相关 API

```ts
import { agreementApi } from '@/api/agreement'

// 根据 code 获取协议内容
const agreement = await agreementApi.getByCode('user_agreement')
```

## 内嵌网页

**分包路径：** `modules/webview`

### 页面

| 页面 | 路径 | 说明 |
|------|------|------|
| 网页视图 | `/modules/webview/pages/webview` | web-view 容器页面 |

### 使用方式

通过 URL 参数传入要展示的网页地址：

```ts
uni.navigateTo({
  url: `/modules/webview/pages/webview?url=${encodeURIComponent('https://example.com')}`,
})
```
