export interface PaymentOrderItem {
    id: number
    order_no: string
    user_id: number
    user_nickname: string
    user_mobile: string
    biz_type: string
    channel: string
    trade_type: string
    subject: string
    amount: string
    refunded_amount: string
    status: string
    trade_no: string
    paid_at: string
    created_at: string
}

export interface PaymentRefundItem {
    id: number
    refund_no: string
    amount: string
    reason: string
    status: string
    channel_refund_no: string
    operator: string
    created_at: string
}

export interface PaymentOrderDetail extends PaymentOrderItem {
    refunds: PaymentRefundItem[]
}
