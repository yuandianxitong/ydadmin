import http from '@/utils/request'

export const configApi = {
  getGlobalConfig: () =>
    http.get<Record<string, any>>('/api/common/config'),
}
