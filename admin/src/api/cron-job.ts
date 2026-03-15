import type { CronJobInfo, CronJobLogInfo, CronJobReq, PageQuery, PageResult } from '@/types/api'
import { myRequest } from '@/utils/request'

export interface CronJobQuery extends PageQuery {
    status?: number
}

/**
 * 定时任务API
 */
export const cronJobApi = {
    /** 获取任务列表 */
    getList(params?: CronJobQuery) {
        return myRequest.get<PageResult<CronJobInfo>>('/adminapi/system/cron-job', { params })
    },

    /** 获取任务详情 */
    getDetail(id: number) {
        return myRequest.get<CronJobInfo>(`/adminapi/system/cron-job/${id}`)
    },

    /** 创建任务 */
    create(data: CronJobReq) {
        return myRequest.post<void>('/adminapi/system/cron-job', data)
    },

    /** 更新任务 */
    update(id: number, data: Partial<CronJobReq>) {
        return myRequest.put<void>(`/adminapi/system/cron-job/${id}`, data)
    },

    /** 删除任务 */
    delete(id: number) {
        return myRequest.delete<void>(`/adminapi/system/cron-job/${id}`)
    },

    /** 更新状态 */
    updateStatus(id: number, status: number) {
        return myRequest.put<void>(`/adminapi/system/cron-job/${id}/status`, { status })
    },

    /** 手动执行 */
    run(id: number) {
        return myRequest.post<{ status: number; output?: string }>(`/adminapi/system/cron-job/${id}/run`)
    },

    /** 获取执行日志 */
    getLogs(id: number, params?: PageQuery) {
        return myRequest.get<PageResult<CronJobLogInfo>>(`/adminapi/system/cron-job/${id}/logs`, {
            params
        })
    },

    /** 清理日志 */
    clearLogs(id: number, keepDays?: number) {
        return myRequest.post<void>(`/adminapi/system/cron-job/${id}/clear-logs`, {
            keep_days: keepDays
        })
    }
}
