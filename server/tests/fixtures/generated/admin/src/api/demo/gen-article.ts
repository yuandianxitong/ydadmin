/**
 * 由代码生成器生成，可按需修改。
 */

import type { PageQuery } from '@/types/common'
import { createCrudApi } from '@/utils/createCrudApi'

export interface GenArticleInfo {
    id: number
    title: string
    summary?: string
    content?: string
    cover_image?: string
    category: 'news' | 'tech' | 'life'
    price: string
    view_count: number
    slug: string
    published_at?: string | null
    status: number
    sort: number
    created_by?: number
    dept_id?: number
    created_at?: string | null
    updated_at?: string | null
    deleted_at?: string | null
}

export interface GenArticleReq {
    title: string
    summary?: string
    content?: string
    cover_image?: string
    category?: 'news' | 'tech' | 'life'
    price?: number
    view_count?: number
    slug: string
    published_at?: string | null
    status?: number
    sort?: number
}

export interface GenArticleQuery extends PageQuery {
    title?: string
    status?: number
}

export const genArticleApi = createCrudApi<GenArticleInfo, GenArticleReq>(
    '/adminapi/demo/gen-article'
)
