# 微信登录

Dev007 移动端提供了完整的微信小程序登录方案，包括微信授权登录和手机号快捷登录两种方式。

## 微信小程序登录流程

```
用户点击登录 → uni.login 获取 code → 后端 API 验证
     ↓                                      ↓
     ↓                              code2Session 换取
     ↓                              openid + session_key
     ↓                                      ↓
     ↓                              查找/创建用户
     ↓                                      ↓
登录成功 ← ← ← ← ← ← ← ← ← 返回 JWT Token + 用户信息
```

### 详细步骤

1. **前端获取 code** — 调用 `uni.login({ provider: 'weixin' })` 获取临时登录凭证 code
2. **传给后端** — 将 code 通过 `authApi.wechatMiniLogin({ code })` 发送给后端
3. **后端换取身份** — 后端使用 code 调用微信 `code2Session` 接口，获取 `openid` 和 `session_key`
4. **查找或创建用户** — 根据 `openid` 查找已有用户或自动创建新用户
5. **返回登录凭证** — 后端生成 JWT Token 和用户信息返回给前端
6. **保存登录状态** — 前端保存 Token 到本地存储，更新用户状态

## uni.login 获取 code

微信小程序的 `uni.login` 调用不需要用户授权，可以静默获取 code：

```ts
const loginRes = await new Promise<UniApp.LoginRes>((resolve, reject) => {
  uni.login({
    provider: 'weixin',
    success: resolve,
    fail: reject,
  })
})

const code = loginRes.code
// code 有效期为 5 分钟，需要尽快传给后端
```

::: warning
code 是一次性的，每次调用 `uni.login` 都会生成新的 code。前一个 code 会在新 code 生成后失效。
:::

## 后端换取 openid / session_key

后端收到 code 后，向微信服务器发送请求获取用户身份标识：

```
GET https://api.weixin.qq.com/sns/jscode2session
    ?appid=APPID
    &secret=APP_SECRET
    &js_code=CODE
    &grant_type=authorization_code
```

**返回数据：**

| 字段 | 说明 |
|------|------|
| openid | 用户在该小程序下的唯一标识 |
| session_key | 会话密钥，用于解密用户数据 |
| unionid | 用户在开放平台下的唯一标识（需绑定开放平台） |

### 后端 API

```
POST /api/auth/wechat-login
```

**请求参数：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| code | `string` | 是 | `uni.login` 获取的临时登录凭证 |

**响应示例：**

```json
{
  "code": 200,
  "message": "登录成功",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIs...",
    "user": {
      "id": 1,
      "nickname": "微信用户",
      "avatar": "",
      "mobile": ""
    }
  }
}
```

## d-wechat-login 组件使用

`d-wechat-login` 组件封装了微信登录的完整流程，只需绑定成功和失败回调即可。

### 基本用法

```vue
<template>
  <d-page>
    <d-wechat-login
      @success="onLoginSuccess"
      @fail="onLoginFail"
    />
  </d-page>
</template>

<script setup lang="ts">
import { useUserStore } from '@/store/user.store'
import type { LoginResult } from '@/types/api'

const userStore = useUserStore()

function onLoginSuccess(result: LoginResult) {
  // 保存登录状态
  userStore.setLoginInfo(result)

  // 跳转到首页或上一页
  uni.switchTab({ url: '/pages/index/index' })
}

function onLoginFail(error: any) {
  console.error('登录失败', error)
}
</script>
```

### 自定义按钮样式

```vue
<d-wechat-login
  text="微信一键登录"
  type="success"
  size="medium"
  :block="false"
  :round="false"
  @success="onLoginSuccess"
  @fail="onLoginFail"
/>
```

### 组件内部流程

`d-wechat-login` 的内部实现步骤：

1. 调用 `uni.login({ provider: 'weixin' })` 获取 code
2. 尝试调用 `uni.getUserProfile({ desc: '用于展示用户信息' })` 获取用户头像和昵称
   - 用户拒绝授权时不影响登录流程
3. 将 code 发送到 `authApi.wechatMiniLogin({ code })` 完成登录
4. 根据结果触发 `success` 或 `fail` 事件

::: info
该组件使用条件编译 `#ifdef MP-WEIXIN`，仅在微信小程序环境下执行登录逻辑。其他环境下会抛出"当前环境不支持微信登录"的错误。
:::

### Props

| 属性 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| text | `string` | `'微信登录'` | 按钮文字 |
| type | `string` | `'primary'` | 按钮类型 |
| size | `string` | `'large'` | 按钮尺寸 |
| block | `boolean` | `true` | 是否块级按钮 |
| round | `boolean` | `true` | 是否圆角 |

### Events

| 事件 | 参数 | 说明 |
|------|------|------|
| success | `LoginResult` | 登录成功，包含 token 和用户信息 |
| fail | `any` | 登录失败，包含错误信息 |

## 手机号快捷登录（d-phone-login）

除了微信授权登录，Dev007 还提供了手机号 + 短信验证码的登录方式，使用 `d-phone-login` 组件实现。

### 基本用法

```vue
<template>
  <d-page>
    <d-phone-login
      @success="onLoginSuccess"
      @fail="onLoginFail"
    />
  </d-page>
</template>
```

### 组件内置功能

- **手机号校验** — 自动校验 11 位手机号格式（`/^1[3-9]\d{9}$/`）
- **发送验证码** — 调用 `authApi.sendSmsCode({ mobile })` 发送短信
- **60 秒倒计时** — 发送后自动倒计时，防止频繁发送
- **登录提交** — 验证码填满后启用登录按钮，调用 `authApi.smsLogin({ mobile, code })`
- **防重复提交** — loading 状态防止重复点击

### 相关后端 API

#### 发送短信验证码

```
POST /api/common/sms-code
```

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| mobile | `string` | 是 | 手机号 |

#### 短信验证码登录

```
POST /api/auth/sms-login
```

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| mobile | `string` | 是 | 手机号 |
| code | `string` | 是 | 短信验证码 |

## 登录页面集成示例

以下是一个完整的登录页面示例，同时支持微信登录和手机号登录：

```vue
<template>
  <d-page :safe-area="true">
    <view class="login-page">
      <view class="logo">
        <image src="/static/logo.png" mode="aspectFit" />
      </view>

      <!-- Tab 切换 -->
      <wd-tabs v-model="activeTab">
        <wd-tab title="手机号登录" name="phone">
          <d-phone-login @success="onLoginSuccess" @fail="onLoginFail" />
        </wd-tab>
        <wd-tab title="微信登录" name="wechat">
          <view class="wechat-section">
            <d-wechat-login @success="onLoginSuccess" @fail="onLoginFail" />
          </view>
        </wd-tab>
      </wd-tabs>

      <!-- 协议 -->
      <d-agreement-check v-model="agreed" />

      <!-- 注册入口 -->
      <view class="register-link" @tap="goRegister">
        还没有账号？立即注册
      </view>
    </view>
  </d-page>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useUserStore } from '@/store/user.store'
import type { LoginResult } from '@/types/api'

const userStore = useUserStore()
const activeTab = ref('phone')
const agreed = ref(false)

function onLoginSuccess(result: LoginResult) {
  if (!agreed.value) {
    uni.showToast({ title: '请先同意用户协议', icon: 'none' })
    return
  }

  userStore.setLoginInfo(result)
  uni.switchTab({ url: '/pages/index/index' })
}

function onLoginFail(error: any) {
  uni.showToast({ title: error?.message || '登录失败', icon: 'none' })
}

function goRegister() {
  uni.navigateTo({ url: '/modules/login/pages/register' })
}
</script>
```

## 登录状态检查

使用 `useAuth` composable 在需要登录的页面进行状态检查：

```ts
import { useAuth } from '@/hooks/useAuth'

const { checkLogin, isLoggedIn } = useAuth()

// 在需要登录才能操作的地方调用
function onNeedAuth() {
  if (!checkLogin()) return  // 未登录会自动跳转登录页

  // 已登录，继续执行业务逻辑
}
```

`checkLogin()` 内部检查 `userStore.isLoggedIn` 状态，未登录时自动跳转到 `/modules/login/pages/login` 页面。
