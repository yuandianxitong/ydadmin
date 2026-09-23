import type { PageResult } from '@/types/common'
import type { PaymentOrderDetail, PaymentOrderItem } from '@/types/payment'
import { myRequest } from '@/utils/request'

export const paymentOrderApi = {
    list(params: Record<string, unknown>) {
        return myRequest.get<PageResult<PaymentOrderItem>>('/adminapi/payment/order/list', { params })
    },

    detail(orderNo: string) {
        return myRequest.get<PaymentOrderDetail>(`/adminapi/payment/order/${orderNo}`)
    },

    refund(data: { order_no: string; amount: string; reason?: string }) {
        return myRequest.post<{ refund_no: string; status: string; amount: string }>(
            '/adminapi/payment/order/refund',
            data
        )
    }
}
