import { get, post } from '~/composables/useRequest'

export const commonApi = {
  getConfig: () =>
    get<Record<string, any>>('/api/common/config'),

  sendSmsCode: (data: { mobile: string }) =>
    post('/api/common/sms-code', data),
}
