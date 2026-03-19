<template>
    <div class="workbench">
        <!-- 欢迎横幅 -->
        <div class="welcome-banner">
            <div class="welcome-banner-pattern"></div>
            <div class="welcome-left">
                <el-avatar :size="52" class="welcome-avatar">
                    {{ (userInfo?.nickname || userInfo?.username)?.[0] || 'A' }}
                </el-avatar>
                <div class="welcome-text">
                    <h2>{{ greetingText }}，{{ userInfo?.nickname || userInfo?.username }}</h2>
                    <p class="welcome-desc">{{ motivationalText }}</p>
                    <p class="welcome-date">
                        <el-icon :size="13"><Calendar /></el-icon>
                        {{ todayDesc }}
                    </p>
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
                <span class="stat-trend" :class="item.trendUp ? 'trend-up' : 'trend-down'">
                    {{ item.trendUp ? '\u2191' : '\u2193' }} {{ item.trend }}
                </span>
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
                                <el-icon :size="22"><component :is="action.icon" /></el-icon>
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
                    <div class="sys-info-grid">
                        <div v-for="item in sysInfo" :key="item.label" class="sys-info-item">
                            <div class="sys-info-icon-wrap" :style="{ background: item.bg, color: item.iconColor }">
                                <el-icon :size="16"><component :is="item.icon" /></el-icon>
                            </div>
                            <div class="sys-info-content">
                                <span class="sys-info-label">{{ item.label }}</span>
                                <span class="sys-info-value">{{ item.value }}</span>
                            </div>
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
    Calendar,
    Clock,
    Cpu,
    Document,
    Lock,
    Menu,
    Monitor,
    Platform,
    Setting,
    Stamp,
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

const motivationalText = computed(() => {
    const h = new Date().getHours()
    if (h < 9) return '\u5F00\u59CB\u5145\u6EE1\u6D3B\u529B\u7684\u4E00\u5929\uFF01'
    if (h < 12) return '\u4E13\u6CE8\u5DE5\u4F5C\uFF0C\u6548\u7387\u6EE1\u6EE1\u3002'
    if (h < 14) return '\u4F11\u606F\u4E00\u4E0B\uFF0C\u4E0B\u5348\u7EE7\u7EED\u52A0\u6CB9\uFF01'
    if (h < 18) return '\u4FDD\u6301\u8282\u594F\uFF0C\u79BB\u4E0B\u73ED\u4E0D\u8FDC\u4E86\u3002'
    return '\u8F9B\u82E6\u4E86\uFF0C\u6CE8\u610F\u4F11\u606F\u3002'
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
        color: 'var(--el-color-primary)',
        trend: '12%',
        trendUp: true
    },
    {
        key: 'role',
        label: t('dashboard.stats.roles'),
        value: stats.value.roleCount,
        icon: UserFilled,
        color: 'var(--el-color-success)',
        trend: '3%',
        trendUp: true
    },
    {
        key: 'menu',
        label: t('dashboard.stats.menus'),
        value: stats.value.menuCount,
        icon: Menu,
        color: 'var(--el-color-warning)',
        trend: '5%',
        trendUp: true
    },
    {
        key: 'login',
        label: t('dashboard.stats.todayLogin'),
        value: stats.value.todayLoginCount,
        icon: Ticket,
        color: 'var(--el-color-danger)',
        trend: '8%',
        trendUp: false
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
    { label: t('dashboard.systemName'), value: t('login.title'), icon: Monitor, iconColor: '#409eff', bg: '#ecf5ff' },
    { label: t('dashboard.systemVersion'), value: 'v1.0.0', icon: Stamp, iconColor: '#67c23a', bg: '#f0f9eb' },
    { label: t('dashboard.backendFramework'), value: 'ThinkPHP 8.0', icon: Cpu, iconColor: '#e6a23c', bg: '#fdf6ec' },
    { label: t('dashboard.frontendFramework'), value: 'Vue 3 + TS', icon: Platform, iconColor: '#409eff', bg: '#ecf5ff' },
    { label: t('dashboard.uiLibrary'), value: 'Element Plus', icon: Setting, iconColor: '#9c27b0', bg: '#f3e5f5' },
    { label: t('dashboard.buildTool'), value: 'Vite', icon: Ticket, iconColor: '#f56c6c', bg: '#fef0f0' }
])

const loginChartOption = computed(() => {
    const dates = stats.value.loginTrend.map((i: any) => i.date)
    const successData = stats.value.loginTrend.map((i: any) => i.count)
    const failData = stats.value.loginFailTrend.map((i: any) => i.count)
    return {
        tooltip: { trigger: 'axis' },
        legend: { data: [t('dashboard.loginSuccess'), t('dashboard.loginFailed')], top: 0, textStyle: { fontSize: 12 } },
        grid: { top: 30, right: 16, bottom: 24, left: 40, containLabel: false },
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
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 24px 28px;
    margin-bottom: 16px;
    background: linear-gradient(
        135deg,
        var(--el-color-primary) 0%,
        var(--el-color-primary-light-3) 50%,
        var(--el-color-primary-light-5) 100%
    );
    border-radius: 6px;
    color: #fff;
    overflow: hidden;
}
.welcome-banner-pattern {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 85% 20%, rgba(255,255,255,0.12) 0%, transparent 50%),
        radial-gradient(circle at 15% 80%, rgba(255,255,255,0.08) 0%, transparent 40%),
        repeating-linear-gradient(
            45deg,
            transparent,
            transparent 20px,
            rgba(255,255,255,0.03) 20px,
            rgba(255,255,255,0.03) 40px
        );
    pointer-events: none;
}
.welcome-left {
    display: flex;
    align-items: center;
    gap: 16px;
    position: relative;
    z-index: 1;
}
.welcome-avatar {
    background: rgba(255, 255, 255, 0.22);
    color: #fff;
    font-size: 20px;
    font-weight: 600;
    border: 2px solid rgba(255,255,255,0.3);
}
.welcome-text h2 {
    margin: 0 0 4px;
    font-size: 18px;
    font-weight: 600;
}
.welcome-desc {
    margin: 0 0 6px;
    font-size: 13px;
    opacity: 0.9;
}
.welcome-date {
    display: flex;
    align-items: center;
    gap: 4px;
    margin: 0;
    font-size: 12px;
    opacity: 0.75;
}
.welcome-right {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    opacity: 0.9;
    white-space: nowrap;
    position: relative;
    z-index: 1;
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
    border-radius: 6px;
    border: 1px solid var(--el-border-color-lighter);
    transition: box-shadow 0.25s ease, transform 0.25s ease;
    position: relative;
    &:hover {
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        transform: translateY(-3px);
    }
}
.stat-icon-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 6px;
    background: color-mix(in srgb, var(--accent) 12%, transparent);
    color: var(--accent);
    flex-shrink: 0;
}
.stat-body {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
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
.stat-trend {
    position: absolute;
    top: 12px;
    right: 14px;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 4px;
    line-height: 1.4;
    &.trend-up {
        color: var(--el-color-success);
        background: color-mix(in srgb, var(--el-color-success) 10%, transparent);
    }
    &.trend-down {
        color: var(--el-color-danger);
        background: color-mix(in srgb, var(--el-color-danger) 10%, transparent);
    }
}

/* 图表行 */
.chart-row {
    margin-bottom: 16px;
}
.chart-card {
    border-radius: 6px;
    height: 100%;
    :deep(.el-card__body) {
        padding: 8px 16px 0;
    }
    :deep(.el-card__header) {
        padding: 14px 16px;
    }
}
.chart {
    height: 260px;
    width: 100%;
}

/* 快捷导航 */
.shortcut-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}
.shortcut-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 16px 8px;
    border-radius: 6px;
    border: 1px solid var(--el-border-color-lighter);
    cursor: pointer;
    transition: background 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
    &:hover {
        background: var(--el-fill-color-light);
        border-color: var(--el-border-color-light);
        transform: translateY(-1px);
    }
}
.shortcut-icon {
    width: 44px;
    height: 44px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.shortcut-text {
    font-size: 12px;
    color: var(--el-text-color-regular);
    font-weight: 500;
}

/* 底部行 */
.bottom-row {
    margin-bottom: 0;
}
.section-card {
    border-radius: 6px;
    margin-bottom: 16px;
    :deep(.el-card__header) {
        padding: 14px 16px;
    }
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

/* 系统信息 - 2列网格布局 */
.sys-info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}
.sys-info-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 6px;
    background: var(--el-fill-color-lighter, #f5f7fa);
    transition: background 0.2s ease;
    &:hover {
        background: var(--el-fill-color-light, #f0f2f5);
    }
}
.sys-info-icon-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    flex-shrink: 0;
}
.sys-info-content {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.sys-info-label {
    font-size: 11px;
    color: var(--el-text-color-secondary);
    line-height: 1.3;
}
.sys-info-value {
    font-size: 13px;
    color: var(--el-text-color-primary);
    font-weight: 600;
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
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
        grid-template-columns: repeat(2, 1fr);
    }
    .sys-info-grid {
        grid-template-columns: 1fr;
    }
}
</style>
