import http from '@/utils/request'

/** 支付渠道 */
export type PayChannel = 'wechat' | 'alipay'

/** 交易类型 */
export type TradeType = 'jsapi' | 'app' | 'h5'

/** 订单查询结果 */
export interface QueryOrderResult {
  order_no: string
  status: 'pending' | 'paid' | 'failed' | 'refunded'
  amount: number
  channel: PayChannel
  paid_at: string | null
}

export const paymentApi = {
  queryOrder: (order_no: string) =>
    http.get<QueryOrderResult>('/api/payment/query', { order_no }),
}
