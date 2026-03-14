# 支付集成

Dev007 移动端内置了完整的支付能力，支持微信支付和支付宝支付两种渠道，适用于微信小程序、App 和 H5 三种场景。

## 支付流程概述

```
用户点击支付 → d-payment-popup 选择支付方式 → usePayment.pay()
    ↓                                              ↓
    ↓                                     创建订单 API（后端）
    ↓                                              ↓
    ↓                                     返回支付参数
    ↓                                              ↓
    ↓                                     uni.requestPayment / H5 跳转
    ↓                                              ↓
    ↓                                     支付结果回调
    ↓                                              ↓
跳转支付结果页 ← ← ← ← ← ← ← ← ← ← checkPayResult 查询订单
```

### 核心流程

1. **选择支付方式** — 通过 `d-payment-popup` 组件让用户选择微信或支付宝
2. **创建支付订单** — 调用后端 API 创建订单，获取支付参数
3. **发起支付** — 根据平台调用 `uni.requestPayment` 或跳转 H5 支付页
4. **查询结果** — 支付完成后查询订单状态，跳转结果页

## 微信支付

### 微信小程序（JSAPI）

在微信小程序内，使用 JSAPI 支付方式：

```ts
// 自动判断为 jsapi 类型
const result = await paymentApi.createOrder({
  order_no: 'ORDER_001',
  channel: 'wechat',
  trade_type: 'jsapi',  // 小程序内自动设置
})

// 调用微信支付
await uni.requestPayment({
  provider: 'wxpay',
  ...result.payment_params,
})
```

### App 支付

App 端使用 App 支付方式：

```ts
const result = await paymentApi.createOrder({
  order_no: 'ORDER_001',
  channel: 'wechat',
  trade_type: 'app',
})

await uni.requestPayment({
  provider: 'wxpay',
  ...result.payment_params,
})
```

### H5 支付

H5 环境下通过跳转微信支付中间页完成支付：

```ts
const result = await paymentApi.createOrder({
  order_no: 'ORDER_001',
  channel: 'wechat',
  trade_type: 'h5',
})

// H5 跳转到支付 URL
window.location.href = result.payment_params.h5_url
```

## 支付宝支付

### App 支付

```ts
const result = await paymentApi.createOrder({
  order_no: 'ORDER_001',
  channel: 'alipay',
  trade_type: 'app',
})

await uni.requestPayment({
  provider: 'alipay',
  ...result.payment_params,
})
```

### H5 支付

```ts
const result = await paymentApi.createOrder({
  order_no: 'ORDER_001',
  channel: 'alipay',
  trade_type: 'h5',
})

// 跳转支付宝收银台
window.location.href = result.payment_params.h5_url
```

## usePayment Composable

`usePayment` 是支付核心 composable，封装了创建订单、发起支付和查询结果的完整流程。

**文件位置：** `uniapp/src/modules/payment/composables/usePayment.ts`

### API

```ts
import { usePayment } from '@/modules/payment/composables/usePayment'

const { loading, pay, checkPayResult } = usePayment()
```

#### `loading`

- **类型：** `Ref<boolean>`
- **说明：** 支付进行中的加载状态

#### `pay(options)`

- **参数：** `{ order_no: string, channel: PayChannel }`
- **返回：** `Promise<boolean>` — 支付是否成功
- **说明：** 自动判断当前平台对应的 `trade_type`（`jsapi` / `app` / `h5`），创建订单并发起支付

```ts
const success = await pay({
  order_no: 'ORDER_001',
  channel: 'wechat',  // 'wechat' | 'alipay'
})
```

#### `checkPayResult(order_no)`

- **参数：** `order_no: string` — 订单号
- **返回：** `Promise<boolean>` — 订单是否已支付
- **说明：** 向后端查询订单支付状态

```ts
const isPaid = await checkPayResult('ORDER_001')
```

### 平台判断

`usePayment` 内部通过工具函数自动判断当前运行平台：

```ts
function getTradeType(): TradeType {
  if (isWeixin()) return 'jsapi'   // 微信小程序 → JSAPI
  if (isApp()) return 'app'        // App → App支付
  return 'h5'                       // 其他 → H5支付
}
```

### 错误处理

- 用户取消支付 — 提示"已取消支付"
- 支付失败 — 提示"支付失败"
- 组件在 `finally` 中重置 loading 状态，确保不会阻塞界面

## d-payment-popup 组件

底部弹出的支付方式选择面板，展示支付金额和支付渠道选项。

### 基本用法

```vue
<template>
  <wd-button @click="showPayment = true">立即支付</wd-button>

  <d-payment-popup
    v-model="showPayment"
    :amount="orderAmount"
    :loading="payLoading"
    @pay="handlePay"
  />
</template>

<script setup lang="ts">
import { ref } from 'vue'
import type { PayChannel } from '@/api/payment'
import { usePayment } from '@/modules/payment/composables/usePayment'

const showPayment = ref(false)
const orderAmount = ref(9900) // 99.00 元（单位：分）
const orderNo = ref('ORDER_001')

const { loading: payLoading, pay } = usePayment()

async function handlePay(channel: PayChannel) {
  const success = await pay({
    order_no: orderNo.value,
    channel,
  })

  if (success) {
    showPayment.value = false
    uni.navigateTo({
      url: `/modules/payment/pages/pay-result?order_no=${orderNo.value}`,
    })
  }
}
</script>
```

### 组件特性

- 金额单位为**分**，内部自动转换为元显示（如 `9900` 显示为 `¥99.00`）
- 默认选中微信支付
- 选中状态带蓝色边框和阴影高亮效果
- 支持 loading 状态，防止重复提交
- 底部安全区域适配

## 支付结果页面

支付完成后跳转到 `/modules/payment/pages/pay-result`，使用 `d-pay-result` 组件展示结果。

### 使用 d-pay-result

```vue
<template>
  <d-page>
    <d-pay-result
      :success="paySuccess"
      :message="paySuccess ? '支付成功' : '支付失败'"
    >
      <wd-button type="primary" block @click="goOrderDetail">
        查看订单
      </wd-button>
      <wd-button block plain custom-style="margin-top: 20rpx;" @click="goHome">
        返回首页
      </wd-button>
    </d-pay-result>
  </d-page>
</template>

<script setup lang="ts">
import { ref, onLoad } from '@dcloudio/uni-app'
import { usePayment } from '../composables/usePayment'

const paySuccess = ref(false)
const { checkPayResult } = usePayment()

onLoad(async (options) => {
  if (options?.order_no) {
    paySuccess.value = await checkPayResult(options.order_no)
  }
})
</script>
```

## 后端支付 API

### 创建支付订单

```
POST /api/payment/create
```

**请求参数：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| order_no | `string` | 是 | 订单号 |
| channel | `string` | 是 | 支付渠道：`wechat` / `alipay` |
| trade_type | `string` | 是 | 交易类型：`jsapi` / `app` / `h5` |

**响应示例：**

```json
{
  "code": 200,
  "message": "success",
  "data": {
    "order_no": "ORDER_001",
    "payment_params": {
      "timeStamp": "1710000000",
      "nonceStr": "abc123",
      "package": "prepay_id=wx20260314...",
      "signType": "RSA",
      "paySign": "..."
    }
  }
}
```

### 查询订单状态

```
GET /api/payment/query
```

**请求参数：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| order_no | `string` | 是 | 订单号 |

**响应示例：**

```json
{
  "code": 200,
  "message": "success",
  "data": {
    "order_no": "ORDER_001",
    "status": "paid",
    "amount": 9900,
    "channel": "wechat",
    "paid_at": "2026-03-14 12:00:00"
  }
}
```

### 订单状态说明

| 状态 | 值 | 说明 |
|------|------|------|
| 待支付 | `pending` | 订单已创建，等待支付 |
| 已支付 | `paid` | 支付成功 |
| 支付失败 | `failed` | 支付失败 |
| 已退款 | `refunded` | 已退款 |

## 完整集成示例

以下是一个完整的商品购买支付流程示例：

```vue
<template>
  <d-page>
    <!-- 商品信息 -->
    <view class="product">
      <text>{{ product.name }}</text>
      <d-price :price="product.price" :original-price="product.originalPrice" />
    </view>

    <!-- 购买按钮 -->
    <wd-button type="primary" block @click="handleBuy">
      立即购买
    </wd-button>

    <!-- 支付弹窗 -->
    <d-payment-popup
      v-model="showPayment"
      :amount="product.price"
      :loading="payLoading"
      @pay="handlePay"
    />
  </d-page>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import type { PayChannel } from '@/api/payment'
import { usePayment } from '@/modules/payment/composables/usePayment'
import { useAuth } from '@/hooks/useAuth'

const { checkLogin } = useAuth()
const { loading: payLoading, pay } = usePayment()
const showPayment = ref(false)

const product = ref({
  name: '商品名称',
  price: 9900,
  originalPrice: 12900,
  orderNo: '',
})

function handleBuy() {
  // 检查登录状态
  if (!checkLogin()) return

  // 先创建业务订单，获取订单号
  // product.value.orderNo = await orderApi.create(...)

  showPayment.value = true
}

async function handlePay(channel: PayChannel) {
  const success = await pay({
    order_no: product.value.orderNo,
    channel,
  })

  if (success) {
    showPayment.value = false
    uni.navigateTo({
      url: `/modules/payment/pages/pay-result?order_no=${product.value.orderNo}`,
    })
  }
}
</script>
```
