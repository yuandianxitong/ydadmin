import type { PageQuery } from './common'

// ========== 用户管理（C 端用户） ==========
export interface UserItem {
    id: number
    nickname: string
    avatar: string
    mobile: string
    balance: string
    points: number
    status: number
    last_login_ip: string
    last_login_time: string
    login_count: number
    created_at: string
}

export interface BalanceLogItem {
    id: number
    user_id: number
    user_nickname: string
    amount: string
    before_balance: string
    after_balance: string
    type: number
    type_text: string
    source: string
    remark: string
    operator_id: number | null
    operator_name: string | null
    created_at: string
}

export interface PointsLogItem {
    id: number
    user_id: number
    user_nickname: string
    points: number
    before_points: number
    after_points: number
    type: number
    type_text: string
    source: string
    remark: string
    operator_id: number | null
    operator_name: string | null
    created_at: string
}

// ========== 版本管理 ==========
export interface AppVersionInfo {
    id: number
    platform: string
    version: string
    version_code: number
    download_url: string
    description?: string
    force_update: number
    status: number
    created_at?: string
    updated_at?: string
}

export interface AppVersionReq {
    platform: string
    version: string
    version_code: number
    download_url: string
    description?: string
    force_update?: number
    status?: number
}

export interface AppVersionQuery extends PageQuery {
    platform?: string
    status?: number
}
