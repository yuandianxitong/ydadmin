import type { AnnouncementInfo, AnnouncementQuery, AnnouncementReq, PageResult } from '@/types/api'
import { myRequest } from '@/utils/request'

/**
 * 公告管理API
 */
export const announcementApi = {
    /** 获取公告列表 */
    getList(params?: AnnouncementQuery) {
        return myRequest.get<PageResult<AnnouncementInfo>>('/adminapi/announcement/list', {
            params
        })
    },

    /** 获取公告详情 */
    getDetail(id: number) {
        return myRequest.get<AnnouncementInfo>(`/adminapi/announcement/detail/${id}`)
    },

    /** 创建公告 */
    create(data: AnnouncementReq) {
        return myRequest.post<void>('/adminapi/announcement', data)
    },

    /** 更新公告 */
    update(id: number, data: Partial<AnnouncementReq>) {
        return myRequest.put<void>(`/adminapi/announcement/${id}`, data)
    },

    /** 更新公告状态 */
    updateStatus(id: number, status: number) {
        return myRequest.put<void>(`/adminapi/announcement/${id}/status`, { status })
    },

    /** 删除公告 */
    delete(id: number) {
        return myRequest.delete<void>(`/adminapi/announcement/${id}`)
    }
}
