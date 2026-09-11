import { get, post, put } from '~/composables/useRequest'
import type { UserInfo } from './auth'

export interface BalanceLogItem {
  id: number
  amount: string
  before_balance: string
  after_balance: string
  type: number
  type_text: string
  remark: string
  created_at: string
}

export interface PointsLogItem {
  id: number
  points: number
  before_points: number
  after_points: number
  type: number
  type_text: string
  remark: string
  created_at: string
}

export const userApi = {
  getProfile: () =>
    get<UserInfo>('/api/user/profile'),

  updateProfile: (data: Partial<Pick<UserInfo, 'nickname' | 'avatar' | 'gender' | 'birthday'>>) =>
    put('/api/user/profile', data),

  changePassword: (data: { old_password: string; new_password: string }) =>
    put('/api/user/change-password', data),

  getBalance: () =>
    get<{ balance: string }>('/api/user/balance'),

  getBalanceLogs: (params?: any) =>
    get<{ list: BalanceLogItem[]; total: number }>('/api/user/balance-logs', params),

  getPoints: () =>
    get<{ points: number }>('/api/user/points'),

  getPointsLogs: (params?: any) =>
    get<{ list: PointsLogItem[]; total: number }>('/api/user/points-logs', params),

  recharge: (data: { amount: number; channel: string }) =>
    post('/api/user/recharge', data),
}
