/**
 * 微信支付工具
 * 封装微信小程序和 App 端的微信支付调用
 */

declare const WeixinJSBridge: any
declare const plus: any

interface WechatPayParams {
  // 微信支付统一下单返回的参数
  appId?: string
  timeStamp: string
  nonceStr: string
  package: string
  signType: string
  paySign: string
  // App 端额外参数
  partnerId?: string
  prepayId?: string
}

interface PayResult {
  success: boolean
  message: string
  errCode?: number
}

/**
 * 调用微信支付
 * 自动判断平台（小程序/App）选择对应支付方式
 */
export function callWechatPay(params: WechatPayParams): Promise<PayResult> {
  // #ifdef MP-WEIXIN
  return new Promise((resolve, reject) => {
    uni.requestPayment({
      provider: 'wxpay',
      timeStamp: params.timeStamp,
      nonceStr: params.nonceStr,
      package: params.package,
      signType: params.signType as 'MD5' | 'HMAC-SHA256' | 'RSA',
      paySign: params.paySign,
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

  // #ifdef APP-PLUS
  return new Promise((resolve, reject) => {
    uni.requestPayment({
      provider: 'wxpay',
      orderInfo: {
        appid: params.appId,
        partnerid: params.partnerId,
        prepayid: params.prepayId,
        package: 'Sign=WXPay',
        noncestr: params.nonceStr,
        timestamp: params.timeStamp,
        sign: params.paySign,
      },
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
    if (typeof WeixinJSBridge !== 'undefined') {
      WeixinJSBridge.invoke('getBrandWCPayRequest', {
        appId: params.appId,
        timeStamp: params.timeStamp,
        nonceStr: params.nonceStr,
        package: params.package,
        signType: params.signType,
        paySign: params.paySign,
      }, (res: any) => {
        if (res.err_msg === 'get_brand_wcpay_request:ok') {
          resolve({ success: true, message: '支付成功' })
        } else if (res.err_msg === 'get_brand_wcpay_request:cancel') {
          resolve({ success: false, message: '用户取消支付', errCode: -1 })
        } else {
          reject({ success: false, message: '支付失败' })
        }
      })
    } else {
      reject({ success: false, message: 'H5 微信支付需要在微信浏览器中打开' })
    }
  })
  // #endif
}

/**
 * 检查当前环境是否支持微信支付
 */
export function isWechatPayAvailable(): boolean {
  // #ifdef MP-WEIXIN
  return true
  // #endif

  // #ifdef APP-PLUS
  return Boolean(plus?.payment?.getChannels)
  // #endif

  // #ifdef H5
  return typeof WeixinJSBridge !== 'undefined'
  // #endif
}
