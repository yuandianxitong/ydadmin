import type { PageQuery } from './common'

// ========== 公告管理 ==========
export interface AnnouncementInfo {
    id: number
    title: string
    content: string
    type: number
    status: number
    sort: number
    publish_at?: string
    admin_id?: number
    created_at?: string
    updated_at?: string
}

export interface AnnouncementReq {
    title: string
    content: string
    type?: number
    status?: number
    sort?: number
}

export interface AnnouncementQuery extends PageQuery {
    status?: number
    type?: number
}

// ========== 协议管理 ==========
export interface AgreementInfo {
    id: number
    title: string
    code: string
    content: string
    status: number
    created_at?: string
    updated_at?: string
}

export interface AgreementReq {
    title: string
    code: string
    content: string
    status?: number
}

export interface AgreementQuery extends PageQuery {
    status?: number
}

// ========== 反馈管理 ==========
export interface FeedbackInfo {
    id: number
    user_id: number
    type: string
    content: string
    images: string | null
    contact: string | null
    status: number
    reply: string | null
    replied_at: string | null
    replied_by: number | null
    created_at: string
}

export interface FeedbackQuery {
    page_no?: number
    page_size?: number
    status?: number | string
    type?: string
    keyword?: string
}
