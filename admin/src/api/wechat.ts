import type { PageQuery, PageResult } from '@/types/common'
import type { AutoReplyInfo, AutoReplyReq, WechatFollowerInfo, WechatMenuData, WechatMenuQueryResult, WechatTemplateSendReq } from '@/types/channel'
import { myRequest } from '@/utils/request'

// 公众号管理
export const officialAccountApi = {
    getMenu() {
        return myRequest.get<WechatMenuQueryResult>('/adminapi/wechat/official/menu')
    },
    createMenu(data: WechatMenuData) {
        return myRequest.post<void>('/adminapi/wechat/official/menu', data)
    },
    deleteMenu() {
        return myRequest.delete<void>('/adminapi/wechat/official/menu')
    },
    sendTemplate(data: WechatTemplateSendReq) {
        return myRequest.post<void>('/adminapi/wechat/official/template/send', data)
    },
    getFollowers(params?: PageQuery) {
        return myRequest.get<PageResult<WechatFollowerInfo>>(
            '/adminapi/wechat/official/followers',
            { params }
        )
    },
    getUserInfo(params: { openid: string }) {
        return myRequest.get<WechatFollowerInfo>('/adminapi/wechat/official/user-info', { params })
    }
}

// 自动回复管理
export const autoReplyApi = {
    getList(params?: PageQuery) {
        return myRequest.get<PageResult<AutoReplyInfo>>('/adminapi/wechat/auto-reply', { params })
    },
    getDetail(id: number) {
        return myRequest.get<AutoReplyInfo>(`/adminapi/wechat/auto-reply/${id}`)
    },
    create(data: AutoReplyReq | Record<string, any>) {
        return myRequest.post<void>('/adminapi/wechat/auto-reply', data)
    },
    update(id: number, data: Partial<AutoReplyReq> | Record<string, any>) {
        return myRequest.put<void>(`/adminapi/wechat/auto-reply/${id}`, data)
    },
    delete(id: number) {
        return myRequest.delete<void>(`/adminapi/wechat/auto-reply/${id}`)
    }
}
