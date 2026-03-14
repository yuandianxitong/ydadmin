import http from '@/utils/request'
import type { UserInfo } from '@/types/api'

export const userApi = {
  getProfile: () =>
    http.get<UserInfo>('/api/user/profile'),

  updateProfile: (data: Partial<Pick<UserInfo, 'nickname' | 'avatar' | 'gender' | 'birthday'>>) =>
    http.put('/api/user/profile', data),

  changePassword: (data: { old_password: string; new_password: string }) =>
    http.put('/api/user/change-password', data),
}
