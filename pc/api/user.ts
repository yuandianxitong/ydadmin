import { get, put } from '~/composables/useRequest'
import type { UserInfo } from './auth'

export const userApi = {
  getProfile: () =>
    get<UserInfo>('/api/user/profile'),

  updateProfile: (data: Partial<Pick<UserInfo, 'nickname' | 'avatar' | 'gender' | 'birthday'>>) =>
    put('/api/user/profile', data),

  changePassword: (data: { old_password: string; new_password: string }) =>
    put('/api/user/change-password', data),
}
