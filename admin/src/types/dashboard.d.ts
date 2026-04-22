// ========== 仪表板 ==========
export interface DashboardTrend {
    value: number
    type: 'up' | 'down'
    unit?: 'percent'
}

export interface DashboardStats {
    adminCount: number
    roleCount: number
    menuCount: number
    configCount: number
    todayLoginCount: number
    todayNewUsers: number
    activeUsers: number
    totalUsers: number
    trends: {
        totalUsers: DashboardTrend
        activeUsers: DashboardTrend
        todayNewUsers: DashboardTrend
        todayLoginCount: DashboardTrend
    }
    operationLogCount: number
    loginTrend: Array<{ date: string; count: number }>
    registerTrend: Array<{ date: string; count: number }>
}

export interface ActivityItem {
    type: 'login_success' | 'login_failed' | 'operation'
    username: string
    description: string
    time: string
    relative_time: string
}

export interface RankingItem {
    rank: number
    username: string
    count: number
}

export interface ActiveRanking {
    period: string
    list: RankingItem[]
}
