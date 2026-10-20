import { paymentApi } from '@/api/payment'

// 下单由具体业务（如 userApi.recharge）完成，这里只查询已有订单的支付结果。
export function usePayment() {
  async function checkPayResult(order_no: string): Promise<boolean> {
    try {
      const result = await paymentApi.queryOrder(order_no)
      return result.status === 'paid'
    } catch {
      return false
    }
  }

  return { checkPayResult }
}
