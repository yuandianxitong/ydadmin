import type { ActiveRanking, ActivityItem, DashboardStats, LoginLogInfo } from '@/types/api'
import { myRequest } from '@/utils/request'

// 获取仪表板统计数据
export const getDashboardStats = (days: number = 7) => {
    return myRequest.get<DashboardStats>('/adminapi/dashboard/stats', { params: { days } })
}

// 获取最近登录日志
export const getRecentLoginLogs = () => {
    return myRequest.get<LoginLogInfo[]>('/adminapi/dashboard/recent-logs')
}

// 获取最近动态
export const getRecentActivities = () => {
    return myRequest.get<ActivityItem[]>('/adminapi/dashboard/recent-activities')
}

// 获取活跃排行
export const getActiveRanking = (period: string = 'day') => {
    return myRequest.get<ActiveRanking>('/adminapi/dashboard/active-ranking', {
        params: { period }
    })
}
