/**
 * 由代码生成器生成，可按需修改。
 */

import type { PageQuery } from '@/types/common'
import { createCrudApi } from '@/utils/createCrudApi'

export interface GenCategoryInfo {
    id: number
    name: string
    is_featured: boolean
    settings?: Record<string, any>
    contact_email?: string
    sort: number
    created_at?: string
    updated_at?: string
}

export interface GenCategoryReq {
    name: string
    is_featured?: boolean
    settings?: Record<string, any>
    contact_email?: string
    sort?: number
}

export interface GenCategoryQuery extends PageQuery {
    name?: string
}

export const { updateStatus: _updateStatus, ...genCategoryApi } = createCrudApi<GenCategoryInfo, GenCategoryReq>(
    '/adminapi/catalog/gen-category'
)
