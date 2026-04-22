import type { PageQuery } from './common'

// ========== 消息管理 ==========
export interface MessageTemplateInfo {
    id: number
    name: string
    code: string
    remark?: string
    status: number
    sms_enabled?: number
    sms_template_id?: string
    sms_content?: string
    wechat_official_enabled?: number
    wechat_official_template_id?: string
    wechat_official_url?: string
    wechat_mini_enabled?: number
    wechat_mini_template_id?: string
    wechat_mini_page?: string
    // Legacy fields
    channel?: string
    content?: string
    variables?: string
    description?: string
    created_at?: string
    updated_at?: string
}

export interface MessageTemplateReq {
    name: string
    code: string
    remark?: string
    status?: number
    sms_enabled?: number
    sms_template_id?: string
    sms_content?: string
    wechat_official_enabled?: number
    wechat_official_template_id?: string
    wechat_official_url?: string
    wechat_mini_enabled?: number
    wechat_mini_template_id?: string
    wechat_mini_page?: string
    // Legacy fields
    channel?: string
    content?: string
    variables?: string
    description?: string
}

export interface MessageLogInfo {
    id: number
    template_id?: number
    channel: string
    to: string
    content: string
    status: number
    error_message?: string
    sent_at?: string
    created_at?: string
}

export interface MessageTemplateQuery extends PageQuery {
    channel?: string
    status?: number
}

export interface MessageLogQuery extends PageQuery {
    channel?: string
    status?: number
}

export interface MessageTestSendReq {
    template_id: number
    to: string
    variables?: Record<string, string>
}

// ========== 微信管理 ==========
export interface AutoReplyInfo {
    id: number
    type: string
    keyword: string
    match_type: string
    content: string
    sort_order?: number
    status: number
    created_at?: string
    updated_at?: string
}

export interface AutoReplyReq {
    type: string
    keyword?: string
    match_type?: string
    content: string
    sort_order?: number
    status?: number
}

export interface WechatFollowerInfo {
    openid: string
    nickname?: string
    sex?: number
    city?: string
    province?: string
    country?: string
    headimgurl?: string
    subscribe_time?: number
}

export interface WechatMenuData {
    button: Array<{
        type: string
        name: string
        key?: string
        url?: string
        sub_button?: Array<{
            type: string
            name: string
            key?: string
            url?: string
        }>
    }>
}

export interface WechatTemplateSendReq {
    touser: string
    template_id: string
    data: Record<string, { value: string; color?: string }>
    url?: string
}

export interface WechatMenuQueryResult {
    selfmenu_info?: {
        button: Array<Record<string, any>>
    }
    [key: string]: any
}
