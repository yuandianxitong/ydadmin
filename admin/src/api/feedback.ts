import { myRequest } from '@/utils/request'
import type { FeedbackInfo, FeedbackQuery } from '@/types/content'

/**
 * 反馈管理API
 */
export const feedbackApi = {
    /** 获取反馈列表 */
    getList(params?: FeedbackQuery) {
        return myRequest.get<any>('/adminapi/feedback/list', { params })
    },

    /** 获取反馈详情 */
    getDetail(id: number) {
        return myRequest.get<FeedbackInfo>(`/adminapi/feedback/detail/${id}`)
    },

    /** 回复反馈 */
    reply(id: number, reply: string) {
        return myRequest.post<void>('/adminapi/feedback/reply', { id, reply })
    },

    /** 关闭反馈 */
    close(id: number) {
        return myRequest.post<void>(`/adminapi/feedback/close/${id}`)
    },

    /** 删除反馈 */
    delete(id: number) {
        return myRequest.delete<void>(`/adminapi/feedback/${id}`)
    }
}
