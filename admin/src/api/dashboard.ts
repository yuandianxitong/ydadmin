import type { DashboardStats, LoginLogInfo } from '@/types/api'
import { myRequest } from '@/utils/request'

// 获取仪表板统计数据
export const getDashboardStats = () => {
    return myRequest.get<DashboardStats>('/adminapi/dashboard/stats')
}

// 获取最近登录日志
export const getRecentLoginLogs = () => {
    return myRequest.get<LoginLogInfo[]>('/adminapi/dashboard/recent-logs')
}
