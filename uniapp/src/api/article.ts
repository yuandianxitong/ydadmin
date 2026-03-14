import { http } from '@/utils/request'
import type { PageResult } from '@/types/api'

export interface ArticleCategory {
  id: number
  name: string
  sort: number
}

export interface ArticleItem {
  id: number
  title: string
  cover: string
  summary: string
  content: string
  category_id: number
  category_name?: string
  author: string
  tags: string
  views: number
  is_top: number
  status: number
  published_at: string
  created_at: string
}

export const articleApi = {
  getList: (params?: { page_no?: number; page_size?: number; category_id?: number }) =>
    http.get<PageResult<ArticleItem>>('/api/article/list', params),

  getDetail: (id: number) =>
    http.get<ArticleItem>(`/api/article/detail/${id}`),

  getCategoryList: () =>
    http.get<ArticleCategory[]>('/api/article-category/list'),
}
