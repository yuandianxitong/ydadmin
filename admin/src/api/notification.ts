import type { PageResult } from '@/types/common'
import type {
    NotificationAdminOption,
    NotificationInfo,
    NotificationQuery,
    NotificationReq
} from '@/types/system'
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

    /** 指定管理员的候选人：数据范围内、启用的管理员，按关键字匹配用户名或昵称，最多 50 条 */
    adminOptions(keyword?: string) {
        return myRequest.get<NotificationAdminOption[]>(
            '/adminapi/system/notification/admin-options',
            { params: keyword ? { keyword } : undefined }
        )
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
