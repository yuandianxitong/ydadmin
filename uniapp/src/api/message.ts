import { http } from '@/utils/request'
import type { PageResult } from '@/types/api'

export interface NotificationInfo {
  id: number
  title: string
  content: string
  type: string
  biz_id: number | null
  extra: Record<string, any>
  created_at: string
  is_read?: boolean
}

export const messageApi = {
  getList: (params: { page_no: number; page_size: number }) =>
    http.get<PageResult<NotificationInfo>>('/api/message/list', params),

  getUnreadCount: () =>
    http.get<{ count: number }>('/api/message/unread-count'),

  markAsRead: (ids?: number[]) =>
    http.post('/api/message/read', { ids }),
}
