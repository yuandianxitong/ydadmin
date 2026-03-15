<template>
    <div class="workbench">
        <!-- 欢迎横幅 -->
        <div class="welcome-banner">
            <div class="welcome-left">
                <el-avatar :size="52" class="welcome-avatar">
                    {{ (userInfo?.nickname || userInfo?.username)?.[0] || 'A' }}
                </el-avatar>
                <div class="welcome-text">
                    <h2>{{ greetingText }}，{{ userInfo?.nickname || userInfo?.username }}</h2>
                    <p>{{ todayDesc }}</p>
                </div>
            </div>
            <div class="welcome-right">
                <el-icon :size="14"><Clock /></el-icon>
                <span>{{ currentTime }}</span>
            </div>
        </div>

        <!-- 统计卡片 -->
        <div class="stat-grid">
            <div
                v-for="item in statCards"
                :key="item.key"
                class="stat-card"
                :style="{ '--accent': item.color }"
            >
                <div class="stat-icon-wrap">
                    <el-icon :size="22"><component :is="item.icon" /></el-icon>
                </div>
                <div class="stat-body">
                    <span class="stat-value">{{ item.value }}</span>
                    <span class="stat-label">{{ item.label }}</span>
                </div>
            </div>
        </div>

        <!-- 图表行 -->
        <el-row :gutter="16" class="chart-row">
            <el-col :xs="24" :lg="16">
                <el-card shadow="never" class="chart-card">
                    <template #header>
                        <div class="card-header">
                            <span class="card-title">{{ $t('dashboard.loginTrend') }}</span>
                        </div>
                    </template>
                    <v-chart :option="loginChartOption" autoresize class="chart" />
                </el-card>
            </el-col>
            <el-col :xs="24" :lg="8">
                <el-card shadow="never" class="chart-card">
                    <template #header>
                        <div class="card-header">
                            <span class="card-title">{{ $t('dashboard.quickNav') }}</span>
                        </div>
                    </template>
                    <div class="shortcut-grid">
                        <div
                            v-for="action in quickActions"
                            :key="action.path"
                            class="shortcut-item"
                            @click="$router.push(action.path)"
                        >
                            <div
                                class="shortcut-icon"
                                :style="{ background: action.bg, color: action.color }"
                            >
                                <el-icon :size="20"><component :is="action.icon" /></el-icon>
                            </div>
                            <span class="shortcut-text">{{ action.label }}</span>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 底部行：登录日志 + 系统信息 -->
        <el-row :gutter="16" class="bottom-row">
            <el-col :xs="24" :lg="16">
                <el-card shadow="never" class="section-card">
                    <template #header>
                        <div class="card-header">
                            <span class="card-title">{{ $t('dashboard.recentLogin') }}</span>
                            <el-button
                                text
                                type="primary"
                                size="small"
                                @click="$router.push('/system/log/login')"
                            >
                                {{ $t('dashboard.viewMore') }}
                            </el-button>
                        </div>
                    </template>
                    <el-table :data="recentLogs" size="small" stripe>
                        <el-table-column :label="$t('dashboard.tableHeaders.username')" prop="username" width="110" />
                        <el-table-column :label="$t('dashboard.tableHeaders.ip')" prop="ip" width="130" />
                        <el-table-column :label="$t('dashboard.tableHeaders.time')" width="160">
                            <template #default="{ row }">{{ formatTime(row.login_time) }}</template>
                        </el-table-column>
                        <el-table-column
                            :label="$t('dashboard.tableHeaders.browser')"
                            prop="browser"
                            min-width="140"
                            show-overflow-tooltip
                        >
                            <template #default="{ row }">{{ row.browser || '-' }}</template>
                        </el-table-column>
                        <el-table-column :label="$t('dashboard.tableHeaders.status')" width="80" align="center">
                            <template #default="{ row }">
                                <el-tag
                                    :type="row.login_result ? 'success' : 'danger'"
                                    size="small"
                                    effect="light"
                                >
                                    {{ row.login_result ? $t('dashboard.success') : $t('dashboard.failed') }}
                                </el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
            <el-col :xs="24" :lg="8">
                <el-card shadow="never" class="section-card">
                    <template #header>
                        <div class="card-header">
                            <span class="card-title">{{ $t('dashboard.systemInfo') }}</span>
                        </div>
                    </template>
                    <div class="sys-info-list">
                        <div v-for="item in sysInfo" :key="item.label" class="sys-info-item">
                            <span class="sys-info-label">{{ item.label }}</span>
                            <span class="sys-info-value">{{ item.value }}</span>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script lang="ts" setup>
import '@/utils/echart'

import {
    Clock,
    Document,
    Lock,
    Menu,
    Setting,
    Ticket,
    User,
    UserFilled
} from '@element-plus/icons-vue'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import VChart from 'vue-echarts'
import { useI18n } from 'vue-i18n'

import { getDashboardStats, getRecentLoginLogs } from '@/api/dashboard'
import { useUserStore } from '@/store/modules/user.store'

const { t } = useI18n()

const userStore = useUserStore()
const userInfo = computed(() => userStore.userInfo)

const greetingText = computed(() => {
    const h = new Date().getHours()
    if (h < 6) return t('dashboard.greeting.night')
    if (h < 9) return t('dashboard.greeting.morning')
    if (h < 12) return t('dashboard.greeting.forenoon')
    if (h < 14) return t('dashboard.greeting.noon')
    if (h < 18) return t('dashboard.greeting.afternoon')
    return t('dashboard.greeting.evening')
})

const todayDesc = computed(() => {
    const n = new Date()
    const weekdayKeys = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'] as const
    const weekday = t(`dashboard.weekdays.${weekdayKeys[n.getDay()]}`)
    return `${n.getFullYear()}${t('dashboard.year')}${n.getMonth() + 1}${t('dashboard.month')}${n.getDate()}${t('dashboard.daySuffix')}${weekday}`
})

const currentTime = ref('')
const stats = ref({
    adminCount: 0,
    roleCount: 0,
    menuCount: 0,
    todayLoginCount: 0,
    loginTrend: [] as any[],
    loginFailTrend: [] as any[]
})
const recentLogs = ref<any[]>([])

const statCards = computed(() => [
    {
        key: 'admin',
        label: t('dashboard.stats.admins'),
        value: stats.value.adminCount,
        icon: User,
        color: 'var(--el-color-primary)'
    },
    {
        key: 'role',
        label: t('dashboard.stats.roles'),
        value: stats.value.roleCount,
        icon: UserFilled,
        color: 'var(--el-color-success)'
    },
    {
        key: 'menu',
        label: t('dashboard.stats.menus'),
        value: stats.value.menuCount,
        icon: Menu,
        color: 'var(--el-color-warning)'
    },
    {
        key: 'login',
        label: t('dashboard.stats.todayLogin'),
        value: stats.value.todayLoginCount,
        icon: Ticket,
        color: 'var(--el-color-danger)'
    }
])

const quickActions = computed(() => [
    { path: '/system/admin', label: t('dashboard.quickNavItems.admin'), icon: User, color: '#409eff', bg: '#ecf5ff' },
    { path: '/system/role', label: t('dashboard.quickNavItems.roleManage'), icon: UserFilled, color: '#67c23a', bg: '#f0f9eb' },
    { path: '/system/menu', label: t('dashboard.quickNavItems.menuManage'), icon: Menu, color: '#e6a23c', bg: '#fdf6ec' },
    { path: '/system/permission', label: t('dashboard.quickNavItems.permManage'), icon: Lock, color: '#f56c6c', bg: '#fef0f0' },
    { path: '/system/config', label: t('dashboard.quickNavItems.systemConfig'), icon: Setting, color: '#909399', bg: '#f4f4f5' },
    {
        path: '/system/log/login',
        label: t('dashboard.quickNavItems.loginLog'),
        icon: Document,
        color: '#9c27b0',
        bg: '#f3e5f5'
    }
])

const sysInfo = computed(() => [
    { label: t('dashboard.systemName'), value: t('login.title') },
    { label: t('dashboard.systemVersion'), value: 'v1.0.0' },
    { label: t('dashboard.backendFramework'), value: 'ThinkPHP 8.0' },
    { label: t('dashboard.frontendFramework'), value: 'Vue 3 + TypeScript' },
    { label: t('dashboard.uiLibrary'), value: 'Element Plus' },
    { label: t('dashboard.buildTool'), value: 'Vite' }
])

const loginChartOption = computed(() => {
    const dates = stats.value.loginTrend.map((i: any) => i.date)
    const successData = stats.value.loginTrend.map((i: any) => i.count)
    const failData = stats.value.loginFailTrend.map((i: any) => i.count)
    return {
        tooltip: { trigger: 'axis' },
        legend: { data: [t('dashboard.loginSuccess'), t('dashboard.loginFailed')], bottom: 0, textStyle: { fontSize: 12 } },
        grid: { top: 10, right: 16, bottom: 36, left: 40 },
        xAxis: {
            type: 'category',
            data: dates,
            boundaryGap: false,
            axisLine: { lineStyle: { color: '#dcdfe6' } },
            axisLabel: { color: '#606266', fontSize: 11 }
        },
        yAxis: {
            type: 'value',
            minInterval: 1,
            axisLine: { show: false },
            axisTick: { show: false },
            splitLine: { lineStyle: { color: '#f0f0f0' } },
            axisLabel: { color: '#909399', fontSize: 11 }
        },
        series: [
            {
                name: t('dashboard.loginSuccess'),
                type: 'line',
                data: successData,
                smooth: true,
                symbol: 'circle',
                symbolSize: 6,
                lineStyle: { width: 2 },
                itemStyle: { color: '#409eff' },
                areaStyle: {
                    color: {
                        type: 'linear',
                        x: 0,
                        y: 0,
                        x2: 0,
                        y2: 1,
                        colorStops: [
                            { offset: 0, color: 'rgba(64,158,255,0.25)' },
                            { offset: 1, color: 'rgba(64,158,255,0.02)' }
                        ]
                    }
                }
            },
            {
                name: t('dashboard.loginFailed'),
                type: 'line',
                data: failData,
                smooth: true,
                symbol: 'circle',
                symbolSize: 6,
                lineStyle: { width: 2 },
                itemStyle: { color: '#f56c6c' },
                areaStyle: {
                    color: {
                        type: 'linear',
                        x: 0,
                        y: 0,
                        x2: 0,
                        y2: 1,
                        colorStops: [
                            { offset: 0, color: 'rgba(245,108,108,0.20)' },
                            { offset: 1, color: 'rgba(245,108,108,0.02)' }
                        ]
                    }
                }
            }
        ]
    }
})

const formatTime = (t: string) => (t ? new Date(t).toLocaleString('zh-CN') : '-')

const updateTime = () => {
    currentTime.value = new Date().toLocaleString('zh-CN')
}

let timer: ReturnType<typeof setInterval> | null = null
onMounted(async () => {
    updateTime()
    timer = setInterval(updateTime, 1000)
    try {
        const [statsRes, logsRes] = await Promise.all([getDashboardStats(), getRecentLoginLogs()])
        stats.value = statsRes.data
        recentLogs.value = logsRes.data
    } catch (e) {
        console.error('Dashboard load error:', e)
    }
})
onUnmounted(() => { if (timer) clearInterval(timer) })
</script>

<style lang="scss" scoped>
.workbench {
    padding: 16px;
    background: var(--el-bg-color-page);
    min-height: 100%;
}

/* 欢迎横幅 */
.welcome-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    margin-bottom: 16px;
    background: linear-gradient(
        135deg,
        var(--el-color-primary) 0%,
        var(--el-color-primary-light-3) 100%
    );
    border-radius: 10px;
    color: #fff;
}
.welcome-left {
    display: flex;
    align-items: center;
    gap: 14px;
}
.welcome-avatar {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
    font-size: 20px;
    font-weight: 600;
}
.welcome-text h2 {
    margin: 0 0 2px;
    font-size: 18px;
    font-weight: 600;
}
.welcome-text p {
    margin: 0;
    font-size: 13px;
    opacity: 0.85;
}
.welcome-right {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    opacity: 0.9;
    white-space: nowrap;
}

/* 统计卡片 */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 16px;
}
.stat-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 20px;
    background: var(--el-bg-color);
    border-radius: 10px;
    border: 1px solid var(--el-border-color-lighter);
    transition: box-shadow 0.2s;
    &:hover {
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    }
}
.stat-icon-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: color-mix(in srgb, var(--accent) 12%, transparent);
    color: var(--accent);
    flex-shrink: 0;
}
.stat-body {
    display: flex;
    flex-direction: column;
}
.stat-value {
    font-size: 22px;
    font-weight: 700;
    color: var(--el-text-color-primary);
    line-height: 1.2;
}
.stat-label {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-top: 2px;
}

/* 图表行 */
.chart-row {
    margin-bottom: 16px;
}
.chart-card {
    border-radius: 10px;
    height: 100%;
    :deep(.el-card__body) {
        padding: 16px;
    }
}
.chart {
    height: 260px;
    width: 100%;
}

/* 快捷导航 */
.shortcut-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}
.shortcut-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 14px 8px;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.15s;
    &:hover {
        background: var(--el-fill-color-light);
    }
}
.shortcut-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.shortcut-text {
    font-size: 12px;
    color: var(--el-text-color-regular);
}

/* 底部行 */
.bottom-row {
    margin-bottom: 0;
}
.section-card {
    border-radius: 10px;
    margin-bottom: 16px;
}
.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    .card-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--el-text-color-primary);
    }
}

/* 系统信息 */
.sys-info-list {
    display: flex;
    flex-direction: column;
}
.sys-info-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid var(--el-border-color-lighter);
    &:last-child {
        border-bottom: none;
    }
}
.sys-info-label {
    font-size: 13px;
    color: var(--el-text-color-secondary);
}
.sys-info-value {
    font-size: 13px;
    color: var(--el-text-color-primary);
    font-weight: 500;
}

/* 响应式 */
@media (max-width: 768px) {
    .stat-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .welcome-banner {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    .shortcut-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
</style>
