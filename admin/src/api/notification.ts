import type { NotificationInfo, NotificationQuery, NotificationReq, PageResult } from '@/types/api'
import { myRequest } from '@/utils/request'

/**
 * 通知管理API
 */
export const notificationApi = {
    /** 获取通知列表（管理端） */
    getList(params?: NotificationQuery) {
        return myRequest.get<PageResult<NotificationInfo>>('/adminapi/system/notification', {
            params
        })
    },

    /** 获取通知详情 */
    getDetail(id: number) {
        return myRequest.get<NotificationInfo>(`/adminapi/system/notification/${id}`)
    },

    /** 发布通知 */
    create(data: NotificationReq) {
        return myRequest.post<void>('/adminapi/system/notification', data)
    },

    /** 更新通知 */
    update(id: number, data: Partial<NotificationReq>) {
        return myRequest.put<void>(`/adminapi/system/notification/${id}`, data)
    },

    /** 删除通知 */
    delete(id: number) {
        return myRequest.delete<void>(`/adminapi/system/notification/${id}`)
    },

    /** 获取我的通知 */
    getMine(params?: { is_read?: number; page?: number; limit?: number }) {
        return myRequest.get<PageResult<NotificationInfo>>('/adminapi/system/notification/mine', {
            params
        })
    },

    /** 获取未读数量 */
    getUnreadCount() {
        return myRequest.get<{ count: number }>('/adminapi/system/notification/unread-count')
    },

    /** 标记已读 */
    markAsRead(id: number) {
        return myRequest.post<void>(`/adminapi/system/notification/${id}/read`)
    },

    /** 全部标记已读 */
    markAllAsRead() {
        return myRequest.post<void>('/adminapi/system/notification/read-all')
    }
}
