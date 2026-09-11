/**
 * 支付宝支付工具
 * 封装 App 端和 H5 端的支付宝支付调用
 */

declare const plus: any

interface AlipayParams {
  // App 端：支付宝返回的 orderString
  orderString?: string
  // H5 端：支付宝收银台 URL
  payUrl?: string
}

interface PayResult {
  success: boolean
  message: string
  errCode?: number
}

/**
 * 调用支付宝支付
 */
export function callAlipay(params: AlipayParams): Promise<PayResult> {
  // #ifdef APP-PLUS
  return new Promise((resolve, reject) => {
    if (!params.orderString) {
      reject({ success: false, message: '缺少支付参数 orderString' })
      return
    }
    uni.requestPayment({
      provider: 'alipay',
      orderInfo: params.orderString,
      success: () => {
        resolve({ success: true, message: '支付成功' })
      },
      fail: (err: any) => {
        if (err.errMsg?.includes('cancel')) {
          resolve({ success: false, message: '用户取消支付', errCode: -1 })
        } else {
          reject({ success: false, message: err.errMsg || '支付失败', errCode: err.errCode })
        }
      },
    })
  })
  // #endif

  // #ifdef H5
  return new Promise((resolve, reject) => {
    if (!params.payUrl) {
      reject({ success: false, message: '缺少支付参数 payUrl' })
      return
    }
    // H5 端跳转到支付宝收银台
    window.location.href = params.payUrl
    // 支付宝会通过 return_url 回调，这里无法直接获取结果
    resolve({ success: true, message: '已跳转支付宝' })
  })
  // #endif

  // #ifdef MP-WEIXIN
  return Promise.reject({ success: false, message: '当前平台不支持支付宝支付' })
  // #endif
}

/**
 * 检查当前环境是否支持支付宝支付
 */
export function isAlipayAvailable(): boolean {
  // #ifdef APP-PLUS
  return Boolean(plus?.payment?.getChannels)
  // #endif

  // #ifdef H5
  return true
  // #endif

  // #ifdef MP-WEIXIN
  return false
  // #endif
}
