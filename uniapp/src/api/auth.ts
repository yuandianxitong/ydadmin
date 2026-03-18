import http from '@/utils/request'
import type { LoginResult, UserInfo } from '@/types/api'

export const authApi = {
  login: (data: { mobile: string; password: string }) =>
    http.post<LoginResult>('/api/auth/login', data),

  smsLogin: (data: { mobile: string; code: string }) =>
    http.post<LoginResult>('/api/auth/sms-login', data),

  sendSmsCode: (data: { mobile: string }) =>
    http.post('/api/common/sms-code', data),

  wechatMiniLogin: (data: { code: string }) =>
    http.post<LoginResult>('/api/auth/wechat-login', data),

  register: (data: { account: string; password: string; password_confirmation: string }) =>
    http.post<LoginResult>('/api/auth/register', data),

  refreshToken: () =>
    http.post<{ token: string }>('/api/auth/refresh-token'),

  getUserInfo: () =>
    http.get<UserInfo>('/api/auth/info'),

  logout: () =>
    http.post('/api/auth/logout'),
}
