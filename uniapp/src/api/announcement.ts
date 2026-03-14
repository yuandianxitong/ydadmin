import { http } from '@/utils/request'
import type { PageResult } from '@/types/api'

export interface AnnouncementItem {
  id: number
  title: string
  content: string
  type: number
  publish_at: string
}

export const announcementApi = {
  getList: (params?: { page_no?: number; page_size?: number }) =>
    http.get<PageResult<AnnouncementItem>>('/api/announcement/list', params),

  getDetail: (id: number) =>
    http.get<AnnouncementItem>(`/api/announcement/detail/${id}`),
}
