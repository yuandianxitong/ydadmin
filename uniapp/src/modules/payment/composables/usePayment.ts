import { ref } from 'vue'
import { paymentApi } from '@/api/payment'
import type { PayChannel, TradeType } from '@/api/payment'
import { isWeixin, isWeixinBrowser, isApp } from '@/utils/platform'

export interface PayOptions {
  order_no: string
  channel: PayChannel
}

function getTradeType(): TradeType {
  if (isWeixin()) return 'jsapi'       // 微信小程序
  if (isWeixinBrowser()) return 'jsapi' // H5 在微信浏览器内
  if (isApp()) return 'app'
  return 'h5'
}

export function usePayment() {
  const loading = ref(false)

  async function pay(options: PayOptions): Promise<boolean> {
    const { order_no, channel } = options
    const trade_type = getTradeType()

    loading.value = true
    try {
      const result = await paymentApi.createOrder({ order_no, channel, trade_type })
      const params = result.payment_params

      // H5 payment: redirect to payment URL
      if (trade_type === 'h5' && params.h5_url) {
        // #ifdef H5
        window.location.href = params.h5_url
        // #endif
        return true
      }

      // Native payment via uni.requestPayment
      const provider = channel === 'wechat' ? 'wxpay' : 'alipay'
      await uni.requestPayment({
        provider,
        ...params,
      })

      return true
    } catch (error: any) {
      // User cancelled payment
      if (error?.errMsg?.includes('cancel')) {
        uni.showToast({ title: '已取消支付', icon: 'none' })
      } else {
        uni.showToast({ title: '支付失败', icon: 'none' })
      }
      return false
    } finally {
      loading.value = false
    }
  }

  async function checkPayResult(order_no: string): Promise<boolean> {
    try {
      const result = await paymentApi.queryOrder(order_no)
      return result.status === 'paid'
    } catch {
      return false
    }
  }

  return { loading, pay, checkPayResult }
}
