import { get, post } from '~/composables/useRequest'

export interface LoginResult {
  token: string
  user_info: {
    id: number
    nickname: string
    avatar: string
    mobile: string
  }
}

export interface UserInfo {
  id: number
  nickname: string
  avatar: string
  mobile: string
  gender: number
  birthday: string
}

export const authApi = {
  login: (data: { account: string; password: string }) =>
    post<LoginResult>('/api/auth/login', data),

  smsLogin: (data: { mobile: string; code: string }) =>
    post<LoginResult>('/api/auth/sms-login', data),

  register: (data: { account: string; password: string; password_confirmation: string }) =>
    post<LoginResult>('/api/auth/register', data),

  wechatLogin: (data: { code: string }) =>
    post<LoginResult>('/api/auth/wechat-web-login', data),

  getUserInfo: () =>
    get<UserInfo>('/api/auth/info'),

  logout: () =>
    post('/api/auth/logout'),
}
