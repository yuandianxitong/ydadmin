import http from '@/utils/request'
import type { UserInfo, PageResult } from '@/types/api'

export const userApi = {
  getProfile: () =>
    http.get<UserInfo>('/api/user/profile'),

  updateProfile: (data: Partial<Pick<UserInfo, 'nickname' | 'avatar' | 'gender' | 'birthday'>>) =>
    http.put('/api/user/profile', data),

  changePassword: (data: { old_password: string; new_password: string }) =>
    http.put('/api/user/change-password', data),

  getBalance: () =>
    http.get<{ balance: string }>('/api/user/balance'),

  getBalanceLogs: (params?: any) =>
    http.get<PageResult<BalanceLogItem>>('/api/user/balance-logs', params),

  getPoints: () =>
    http.get<{ points: number }>('/api/user/points'),

  getPointsLogs: (params?: any) =>
    http.get<PageResult<PointsLogItem>>('/api/user/points-logs', params),

  recharge: (data: { amount: number; channel: string }) =>
    http.post<RechargeResult>('/api/user/recharge', data),
}

/** 充值接口返回结构（与后端 PaymentService.createOrder 对齐） */
export interface RechargeResult {
  order_no: string
  payment_id: number
  payment_data: {
    trade_type: 'jsapi' | 'app' | 'h5' | 'native'
    data: Record<string, any>
  }
}

/** 余额明细项 */
export interface BalanceLogItem {
  id: number
  type: number
  type_text: string
  amount: string
  before_balance: string
  after_balance: string
  remark: string
  created_at: string
}

/** 积分明细项 */
export interface PointsLogItem {
  id: number
  type: number
  type_text: string
  points: number
  before_points: number
  after_points: number
  remark: string
  created_at: string
}
