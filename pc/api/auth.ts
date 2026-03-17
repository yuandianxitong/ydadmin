import { get, post } from '~/composables/useRequest'

export interface LoginResult {
  token: string
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
  login: (data: { mobile: string; password: string }) =>
    post<LoginResult>('/api/auth/login', data),

  register: (data: { mobile: string; password: string; code: string }) =>
    post<LoginResult>('/api/auth/register', data),

  getUserInfo: () =>
    get<UserInfo>('/api/auth/info'),

  logout: () =>
    post('/api/auth/logout'),
}
