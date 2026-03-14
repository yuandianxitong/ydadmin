import http from '@/utils/request'

/** 支付渠道 */
export type PayChannel = 'wechat' | 'alipay'

/** 交易类型 */
export type TradeType = 'jsapi' | 'app' | 'h5'

/** 创建订单参数 */
export interface CreateOrderParams {
  order_no: string
  channel: PayChannel
  trade_type: TradeType
}

/** 创建订单返回 */
export interface CreateOrderResult {
  /** 支付参数，传给 uni.requestPayment */
  payment_params: Record<string, any>
  /** 订单号 */
  order_no: string
}

/** 订单查询结果 */
export interface QueryOrderResult {
  order_no: string
  status: 'pending' | 'paid' | 'failed' | 'refunded'
  amount: number
  channel: PayChannel
  paid_at: string | null
}

export const paymentApi = {
  createOrder: (data: CreateOrderParams) =>
    http.post<CreateOrderResult>('/api/payment/create', data),

  queryOrder: (order_no: string) =>
    http.get<QueryOrderResult>('/api/payment/query', { order_no }),
}
