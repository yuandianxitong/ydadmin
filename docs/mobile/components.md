# 自定义组件

Dev007 移动端提供了一系列开箱即用的业务组件，统一以 `d-` 为前缀。通过 easycom 机制自动注册，无需手动导入即可在模板中使用。

## easycom 自动注册

在 `pages.json` 中已配置 easycom 规则：

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

所有 `d-` 前缀的组件直接在模板中使用即可，无需 `import`：

```vue
<template>
  <d-page>
    <d-empty text="暂无数据" />
  </d-page>
</template>
```

## d-page 页面容器

页面根容器组件，提供统一的背景色、内边距和安全区域适配。

### Props

| 属性 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| safeArea | `boolean` | `true` | 是否启用底部安全区域适配 |

### 使用示例

```vue
<template>
  <d-page>
    <!-- 页面内容 -->
  </d-page>
</template>
```

```vue
<!-- 不需要底部安全区域 -->
<d-page :safe-area="false">
  <!-- 内容 -->
</d-page>
```

## d-empty 空状态

数据为空时的占位提示组件，支持自定义图标、文案和操作按钮。

### Props

| 属性 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| icon | `string` | — | 图标图片路径 |
| text | `string` | `'暂无数据'` | 提示文字 |
| actionText | `string` | — | 操作按钮文字，不传则不显示按钮 |

### Events

| 事件 | 说明 |
|------|------|
| action | 点击操作按钮时触发 |

### 使用示例

```vue
<!-- 基础用法 -->
<d-empty />

<!-- 自定义文案和操作按钮 -->
<d-empty
  icon="/static/empty-order.png"
  text="暂无订单"
  action-text="去逛逛"
  @action="goShopping"
/>
```

## d-list-loader 列表加载器

列表底部加载状态组件，配合 `usePaging` 使用。自动展示"加载中"、"没有更多了"和空状态三种状态。

### Props

| 属性 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| loading | `boolean` | `false` | 是否加载中 |
| finished | `boolean` | `false` | 是否加载完成 |
| total | `number` | `0` | 总数据量 |
| emptyText | `string` | `'暂无数据'` | 数据为空时的提示文字 |

### 显示逻辑

- `loading === true` -> 显示加载动画和"加载中..."
- `finished === true && total > 0` -> 显示"没有更多了"
- `finished === true && total === 0` -> 显示空状态组件

### 使用示例

```vue
<template>
  <d-page>
    <view v-for="item in list" :key="item.id">
      {{ item.title }}
    </view>
    <d-list-loader
      :loading="loading"
      :finished="finished"
      :total="total"
      empty-text="暂无消息"
    />
  </d-page>
</template>

<script setup lang="ts">
import { usePaging } from '@/hooks/usePaging'

const { list, loading, finished, total, getList, refresh } = usePaging({
  fetchFun: api.getList,
})

onLoad(() => getList())
onReachBottom(() => getList())
onPullDownRefresh(() => refresh().then(() => uni.stopPullDownRefresh()))
</script>
```

## d-payment-popup 支付弹窗

底部弹出式支付方式选择组件，展示金额并提供微信支付和支付宝支付两种选项。

### Props

| 属性 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| modelValue | `boolean` | — | 是否显示弹窗，支持 `v-model` |
| amount | `number` | — | 支付金额，单位：分 |
| loading | `boolean` | `false` | 确认按钮加载状态 |

### Events

| 事件 | 参数 | 说明 |
|------|------|------|
| update:modelValue | `boolean` | 弹窗显示状态变化 |
| pay | `PayChannel` (`'wechat'` \| `'alipay'`) | 点击确认支付时触发，返回选择的支付渠道 |

### 使用示例

```vue
<template>
  <d-payment-popup
    v-model="showPayment"
    :amount="orderAmount"
    :loading="payLoading"
    @pay="handlePay"
  />
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { usePayment } from '@/modules/payment/composables/usePayment'

const showPayment = ref(false)
const orderAmount = ref(9900) // 99.00 元

const { loading: payLoading, pay } = usePayment()

async function handlePay(channel: PayChannel) {
  const success = await pay({ order_no: 'ORDER_001', channel })
  if (success) {
    showPayment.value = false
    uni.navigateTo({ url: '/modules/payment/pages/pay-result?order_no=ORDER_001' })
  }
}
</script>
```

## d-pay-result 支付结果

支付结果展示组件，用于支付结果页面，展示成功或失败状态的图标和消息。

### Props

| 属性 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| success | `boolean` | — | 是否支付成功 |
| message | `string` | `''` | 提示消息 |

### Slots

| 插槽 | 说明 |
|------|------|
| default | 底部额外内容区域，可放置操作按钮等 |

### 使用示例

```vue
<d-pay-result :success="true" message="支付成功">
  <wd-button type="primary" block @click="goOrderDetail">
    查看订单
  </wd-button>
  <wd-button block @click="goHome">
    返回首页
  </wd-button>
</d-pay-result>
```

## d-price 价格展示

价格显示组件，自动将分为单位的金额格式化为元，支持原价（划线价）展示。

### Props

| 属性 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| price | `number` | — | 价格，单位：分 |
| originalPrice | `number` | — | 原价（划线价），单位：分，可选 |

### 使用示例

```vue
<!-- 显示 ¥99.00 -->
<d-price :price="9900" />

<!-- 显示 ¥79.00 ¥99.00（带划线价） -->
<d-price :price="7900" :original-price="9900" />
```

::: tip
组件内部自动将分转换为元显示。传入 `9900` 将显示为 `¥99.00`。整数部分和小数部分使用不同字号，突出价格信息。
:::

## d-avatar-upload 头像上传

头像选择和上传组件，支持 `v-model` 双向绑定。点击后调起系统相册选择图片并上传。

### Props

| 属性 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| modelValue | `string` | — | 头像图片路径，支持 `v-model` |

### Events

| 事件 | 参数 | 说明 |
|------|------|------|
| update:modelValue | `string` | 上传成功后返回图片路径 |

### 使用示例

```vue
<d-avatar-upload v-model="userInfo.avatar" />
```

### 内部实现

- 使用 `useUpload` composable，限制最大上传 5MB
- 自动调用 `appStore.getImageUrl()` 拼接完整显示 URL
- 未设置头像时显示默认头像 `/static/default-avatar.png`
- 头像底部覆盖半透明相机图标提示可更换

## d-agreement-check 协议勾选

协议同意勾选组件，用于登录、注册等需要用户同意协议的场景。

### Props

| 属性 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| modelValue | `boolean` | — | 是否勾选，支持 `v-model` |
| termsUrl | `string` | `'/modules/agreement/pages/agreement?code=user_agreement'` | 用户协议页面路径 |
| privacyUrl | `string` | `'/modules/agreement/pages/agreement?code=privacy_policy'` | 隐私政策页面路径 |

### 使用示例

```vue
<template>
  <d-agreement-check v-model="agreed" />
  <wd-button :disabled="!agreed" @click="handleSubmit">提交</wd-button>
</template>

<script setup lang="ts">
const agreed = ref(false)
</script>
```

点击"《隐私政策》"和"《用户协议》"链接会跳转到协议展示页面，不影响勾选状态的切换。

## d-wechat-login 微信登录

微信小程序一键登录按钮组件。仅在微信小程序环境下生效，其他环境调用时会提示不支持。

### Props

| 属性 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| text | `string` | `'微信登录'` | 按钮文字 |
| type | `string` | `'primary'` | 按钮类型 |
| size | `string` | `'large'` | 按钮尺寸 |
| block | `boolean` | `true` | 是否为块级按钮 |
| round | `boolean` | `true` | 是否圆角 |

### Events

| 事件 | 参数 | 说明 |
|------|------|------|
| success | `LoginResult` | 登录成功，返回用户信息和 token |
| fail | `any` | 登录失败 |

### 使用示例

```vue
<d-wechat-login
  text="微信一键登录"
  @success="onLoginSuccess"
  @fail="onLoginFail"
/>
```

### 内部流程

1. 调用 `uni.login({ provider: 'weixin' })` 获取 code
2. 尝试调用 `uni.getUserProfile` 获取用户信息（用户拒绝不影响登录）
3. 将 code 传给 `authApi.wechatMiniLogin` 完成后端登录
4. 触发 `success` 或 `fail` 事件

::: tip
详细微信登录流程请参阅 [微信登录](./wechat-login.md)。
:::

## d-phone-login 手机号登录

手机号 + 短信验证码登录组件，集成发送验证码、倒计时和登录的完整流程。

### Props

| 属性 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| placeholder | `string` | `'请输入手机号'` | 手机号输入框占位文字 |
| codeLength | `number` | `6` | 验证码长度 |

### Events

| 事件 | 参数 | 说明 |
|------|------|------|
| success | `LoginResult` | 登录成功 |
| fail | `any` | 登录或发送验证码失败 |

### 使用示例

```vue
<d-phone-login @success="onLoginSuccess" @fail="onLoginFail" />
```

### 内置功能

- 手机号格式校验（`/^1[3-9]\d{9}$/`）
- 验证码发送后 60 秒倒计时
- 倒计时期间禁用发送按钮
- 验证码填满指定长度后才可点击登录
- 加载状态防止重复提交

## d-image-preview 图片预览

图片网格展示组件，支持点击预览大图和删除操作。

### Props

| 属性 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| images | `string[]` | — | 图片 URL 列表 |
| columns | `number` | `3` | 每行列数 |
| gap | `string` | `'16rpx'` | 图片间距 |
| previewable | `boolean` | `true` | 是否支持点击预览大图 |
| deletable | `boolean` | `false` | 是否显示删除按钮 |

### Events

| 事件 | 参数 | 说明 |
|------|------|------|
| delete | `number` | 点击删除按钮时触发，返回图片索引 |

### 使用示例

```vue
<!-- 基础图片展示 -->
<d-image-preview :images="imageList" />

<!-- 可删除的图片网格 -->
<d-image-preview
  :images="imageList"
  :columns="4"
  gap="12rpx"
  deletable
  @delete="handleDelete"
/>
```

### 特性

- 自适应宽度，根据列数自动计算每张图片尺寸
- 点击图片调用 `uni.previewImage` 预览大图
- 图片加载失败时显示兜底图片
- 删除按钮位于图片右上角
