import { http } from '@/utils/request'
import type { PageResult } from '@/types/api'

export interface FeedbackInfo {
  id: number
  type: string
  content: string
  images: string[]
  contact: string
  status: number
  reply: string | null
  replied_at: string | null
  created_at: string
}

export const feedbackApi = {
  submit: (data: {
    type: string
    content: string
    images?: string[]
    contact?: string
  }) => http.post<FeedbackInfo>('/api/feedback/submit', data),

  getList: (params: { page_no: number; page_size: number }) =>
    http.get<PageResult<FeedbackInfo>>('/api/feedback/list', params),

  getDetail: (id: number) =>
    http.get<FeedbackInfo>(`/api/feedback/detail/${id}`),
}
