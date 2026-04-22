<script setup lang="ts">
import '@/utils/echart'

import {
    Document,
    Key,
    Lock,
    Menu as MenuIcon,
    Monitor,
    Plus,
    Setting,
    TrendCharts,
    User,
    UserFilled
} from '@element-plus/icons-vue'
import { computed, onMounted, ref } from 'vue'
import VChart from 'vue-echarts'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import { getDashboardStats } from '@/api/dashboard'
import useSettingStore from '@/store/modules/settings.store'
import type { DashboardStats } from '@/types/dashboard'

const router = useRouter()
const { t } = useI18n()
const settingStore = useSettingStore()

// Dark mode detection for ECharts
const isDark = computed(() => {
    if (settingStore.themeMode === 'dark') return true
    if (settingStore.themeMode === 'light') return false
    return document.documentElement.classList.contains('dark')
})

const chartColors = computed(() =>
    isDark.value
        ? {
              surface: '#1a1b1e',
              tooltipBg: 'rgba(30,31,34,0.96)',
              tooltipBorder: '#2e2f33',
              tooltipText: '#e5e7eb',
              axisLine: '#2e2f33',
              splitLine: '#252629',
              axisLabel: '#6b7280',
              centerLabel: P.blue.to
          }
        : {
              surface: '#fff',
              tooltipBg: 'rgba(255,255,255,0.96)',
              tooltipBorder: '#eee',
              tooltipText: '#333',
              axisLine: '#e5e7eb',
              splitLine: '#f0f0f0',
              axisLabel: '#94a3b8',
              centerLabel: P.blue.from
          }
)

// State
const stats = ref<DashboardStats | null>(null)
const trendDays = ref(7)

// Data loading
const loadStats = async () => {
    try {
        const res = await getDashboardStats(trendDays.value)
        stats.value = res.data
    } catch (e) {
        console.error('加载仪表盘数据失败:', e)
    }
}

const switchTrendDays = (days: number) => {
    trendDays.value = days
    loadStats()
}

onMounted(() => {
    loadStats()
})

// 仪表盘调色板（单一来源，KPI、快捷导航、图表共用）
const P = {
    blue:   { from: '#4C84FF', to: '#6C9FFF', dark: '#3A6FE0' },
    teal:   { from: '#36CFC9', to: '#5CE0DB', dark: '#28B5A8' },
    amber:  { from: '#F5A623', to: '#F7C164', dark: '#E08E10' },
    red:    { from: '#FF6B6B', to: '#FF8E8E', dark: '#E04545' },
    purple: { from: '#9B59B6', to: '#BB77D0' },
    green:  { from: '#52c41a' },
    yellow: { from: '#faad14' }
}
const grad = (c: { from: string; to?: string }, dir = '135deg') => `linear-gradient(${dir}, ${c.from}, ${c.to || c.from})`

// KPI Cards config
const kpiCards = computed(() => {
    if (!stats.value) return []
    const s = stats.value
    return [
        {
            label: t('dashboard.totalUsers'),
            value: s.totalUsers,
            trend: s.trends.totalUsers,
            gradient: grad(P.blue, 'to right'),
            icon: User
        },
        {
            label: t('dashboard.activeUsers'),
            value: s.activeUsers,
            trend: s.trends.activeUsers,
            gradient: grad(P.teal, 'to right'),
            icon: TrendCharts
        },
        {
            label: t('dashboard.todayNew'),
            value: s.todayNewUsers,
            trend: s.trends.todayNewUsers,
            gradient: grad(P.amber, 'to right'),
            icon: Plus
        },
        {
            label: t('dashboard.todayLogin'),
            value: s.todayLoginCount,
            trend: s.trends.todayLoginCount,
            gradient: grad(P.red, 'to right'),
            icon: Key
        }
    ]
})

// Quick nav items
const quickNavItems = [
    { label: 'dashboard.quickNavItems.userManage', icon: User, route: '/user/list', gradient: grad(P.blue) },
    { label: 'dashboard.quickNavItems.roleManage', icon: UserFilled, route: '/system/role', gradient: grad(P.teal) },
    { label: 'dashboard.quickNavItems.menuManage', icon: MenuIcon, route: '/system/menu', gradient: grad(P.amber) },
    { label: 'dashboard.quickNavItems.permManage', icon: Lock, route: '/system/permission', gradient: grad(P.purple) },
    { label: 'dashboard.quickNavItems.systemConfig', icon: Setting, route: '/system/config', gradient: grad(P.teal) },
    { label: 'dashboard.quickNavItems.loginLog', icon: Document, route: '/system/admin_login_log', gradient: grad(P.red) },
    { label: 'dashboard.quickNavItems.article', icon: Document, route: '/content/article', gradient: grad(P.blue) },
    { label: 'dashboard.quickNavItems.announcement', icon: Monitor, route: '/content/announcement', gradient: grad(P.amber) }
]

// Donut chart option
const donutOption = computed(() => {
    if (!stats.value) return {}
    const s = stats.value
    const total = s.totalUsers + s.roleCount + s.menuCount + s.configCount
    return {
        tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
        legend: { show: false },
        series: [
            {
                type: 'pie',
                radius: ['50%', '72%'],
                center: ['50%', '50%'],
                avoidLabelOverlap: false,
                label: {
                    show: true,
                    position: 'center',
                    formatter: () => total.toString(),
                    fontSize: 28,
                    fontWeight: 700,
                    color: chartColors.value.centerLabel
                },
                emphasis: {
                    label: { show: true, fontSize: 28, fontWeight: 700 }
                },
                itemStyle: {
                    borderRadius: 4,
                    borderColor: chartColors.value.surface,
                    borderWidth: 2
                },
                data: [
                    {
                        value: s.totalUsers,
                        name: t('dashboard.users'),
                        itemStyle: { color: P.blue.from }
                    },
                    {
                        value: s.roleCount,
                        name: t('dashboard.roles'),
                        itemStyle: { color: P.green.from }
                    },
                    {
                        value: s.menuCount,
                        name: t('dashboard.menus'),
                        itemStyle: { color: P.yellow.from }
                    },
                    {
                        value: s.configCount,
                        name: t('dashboard.configs'),
                        itemStyle: { color: P.purple.from }
                    }
                ]
            }
        ]
    }
})

// Trend chart option builder
const buildTrendOption = (data: Array<{ date: string; count: number }>, color: string) => ({
    tooltip: {
        trigger: 'axis',
        backgroundColor: chartColors.value.tooltipBg,
        borderColor: chartColors.value.tooltipBorder,
        textStyle: { color: chartColors.value.tooltipText, fontSize: 13 }
    },
    grid: { top: 16, right: 20, bottom: 30, left: 48 },
    xAxis: {
        type: 'category',
        data: data.map((i) => i.date),
        axisLine: { lineStyle: { color: chartColors.value.axisLine } },
        axisLabel: { color: chartColors.value.axisLabel, fontSize: 12 },
        axisTick: { show: false }
    },
    yAxis: {
        type: 'value',
        minInterval: 1,
        splitLine: { lineStyle: { type: 'dashed', color: chartColors.value.splitLine } },
        axisLabel: { color: chartColors.value.axisLabel, fontSize: 12 }
    },
    series: [
        {
            type: 'line',
            data: data.map((i) => i.count),
            smooth: true,
            symbol: 'circle',
            symbolSize: 7,
            lineStyle: { color, width: 3 },
            itemStyle: { color, borderWidth: 2, borderColor: '#fff' },
            areaStyle: {
                color: {
                    type: 'linear',
                    x: 0,
                    y: 0,
                    x2: 0,
                    y2: 1,
                    colorStops: [
                        { offset: 0, color: color + '40' },
                        { offset: 1, color: color + '05' }
                    ]
                }
            }
        }
    ]
})

const registerTrendOption = computed(() => {
    if (!stats.value?.registerTrend) return {}
    return buildTrendOption(stats.value.registerTrend, P.blue.from)
})

const loginTrendOption = computed(() => {
    if (!stats.value?.loginTrend) return {}
    return buildTrendOption(stats.value.loginTrend, P.teal.from)
})

const navigateTo = (path: string) => router.push(path)
</script>

<template>
    <div class="dashboard">
        <!-- KPI Cards Row -->
        <div class="kpi-row">
            <div
                v-for="(card, i) in kpiCards"
                :key="i"
                class="kpi-card"
                :style="{ background: card.gradient }"
            >
                <div class="kpi-content">
                    <span class="kpi-label">{{ card.label }}</span>
                    <span class="kpi-value">{{ card.value?.toLocaleString() }}</span>
                    <span class="kpi-trend">
                        <template v-if="card.trend">
                            {{ card.trend.type === 'up' ? '↑' : '↓' }}
                            {{ card.trend.value }}{{ card.trend.unit === 'percent' ? '%' : '' }}
                            {{ t('dashboard.comparedLastWeek') }}
                        </template>
                    </span>
                </div>
                <el-icon class="kpi-icon"><component :is="card.icon" /></el-icon>
            </div>
        </div>

        <!-- Middle Section: Resource Overview + Quick Nav -->
        <div class="middle-section">
            <!-- Resource Overview (Donut) -->
            <div class="glass-card resource-card">
                <div class="card-header">
                    <span class="card-title">{{ t('dashboard.resourceOverview') }}</span>
                </div>
                <div class="resource-content">
                    <v-chart class="donut-chart" :option="donutOption" autoresize />
                    <div class="resource-legend">
                        <template v-if="stats">
                            <div
                                v-for="item in [
                                    {
                                        name: t('dashboard.users'),
                                        value: stats.totalUsers,
                                        color: P.blue.from
                                    },
                                    {
                                        name: t('dashboard.roles'),
                                        value: stats.roleCount,
                                        color: P.green.from
                                    },
                                    {
                                        name: t('dashboard.menus'),
                                        value: stats.menuCount,
                                        color: P.yellow.from
                                    },
                                    {
                                        name: t('dashboard.configs'),
                                        value: stats.configCount,
                                        color: P.purple.from
                                    }
                                ]"
                                :key="item.name"
                                class="legend-item"
                            >
                                <span class="legend-dot" :style="{ background: item.color }"></span>
                                <span class="legend-name">{{ item.name }}</span>
                                <span class="legend-value">{{ item.value }}</span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Quick Nav -->
            <div class="glass-card quicknav-card">
                <div class="card-header">
                    <span class="card-title">{{ t('dashboard.quickNav') }}</span>
                </div>
                <div class="quick-nav-grid">
                    <div
                        v-for="(item, i) in quickNavItems"
                        :key="i"
                        class="nav-item"
                        @click="navigateTo(item.route)"
                    >
                        <div class="nav-icon" :style="{ background: item.gradient }">
                            <el-icon :size="20" color="#fff"><component :is="item.icon" /></el-icon>
                        </div>
                        <span class="nav-label">{{ t(item.label) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Trend Overview -->
        <div class="glass-card trend-card">
            <div class="card-header">
                <span class="card-title">{{ t('dashboard.trendOverview') }}</span>
                <div class="period-tabs">
                    <span
                        class="period-tab"
                        :class="{ active: trendDays === 7 }"
                        @click="switchTrendDays(7)"
                        >{{ t('dashboard.trendPeriod.week') }}</span
                    >
                    <span
                        class="period-tab"
                        :class="{ active: trendDays === 30 }"
                        @click="switchTrendDays(30)"
                        >{{ t('dashboard.trendPeriod.month') }}</span
                    >
                </div>
            </div>
            <div class="trend-charts">
                <div class="trend-chart-wrapper">
                    <div class="trend-label">{{ t('dashboard.registerTrend') }}</div>
                    <v-chart class="trend-chart" :option="registerTrendOption" autoresize />
                </div>
                <div class="trend-chart-wrapper">
                    <div class="trend-label">{{ t('dashboard.loginTrend') }}</div>
                    <v-chart class="trend-chart" :option="loginTrendOption" autoresize />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped lang="scss">
.dashboard {
    padding: 0;
    min-height: 100%;
}

// ===== KPI Cards =====
.kpi-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.kpi-card {
    position: relative;
    border-radius: 8px;
    padding: 28px 24px;
    color: #fff;
    overflow: hidden;
    transition: all 0.25s ease;
    cursor: default;
    &:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
    }
}

.kpi-content {
    display: flex;
    flex-direction: column;
    position: relative;
    z-index: 1;
}

.kpi-label {
    font-size: 14px;
    opacity: 0.85;
    margin-bottom: 12px;
    letter-spacing: 0.5px;
}

.kpi-value {
    font-size: 36px;
    font-weight: 700;
    margin-bottom: 10px;
    letter-spacing: -0.5px;
}

.kpi-trend {
    font-size: 13px;
    opacity: 0.75;
}

.kpi-icon {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 64px;
    opacity: 0.15;
}

// ===== Glass Card =====
.glass-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 24px;
    box-shadow: var(--shadow-sm);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.card-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--color-text-primary);
}

// ===== Middle Section =====
.middle-section {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.resource-card {
    flex: 1;
}

.quicknav-card {
    flex: 1;
}

// ===== Resource Overview =====
.resource-content {
    display: flex;
    align-items: center;
    gap: 32px;
}

.donut-chart {
    width: 200px;
    height: 200px;
    flex-shrink: 0;
}

.resource-legend {
    flex: 1;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--color-divider);

    &:last-child {
        border-bottom: none;
    }
}

.legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}

.legend-name {
    font-size: 14px;
    color: var(--color-text-tertiary);
    flex: 1;
}

.legend-value {
    font-size: 16px;
    font-weight: 600;
    color: var(--color-text-primary);
}

// ===== Quick Nav =====
.quick-nav-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}

.nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 20px 8px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.25s ease;

    &:hover {
        background: var(--color-brand-ghost);
        .nav-icon {
            transform: scale(1.08);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        }
    }
}

.nav-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.25s ease;
}

.nav-label {
    font-size: 13px;
    color: var(--color-text-primary);
    font-weight: 500;
}

// ===== Trend =====
.trend-card {
    margin-bottom: 20px;
}

.period-tabs {
    display: flex;
    gap: 4px;
}

.period-tab {
    font-size: 12px;
    padding: 4px 14px;
    border-radius: 12px;
    background: var(--gray-200);
    color: var(--color-text-tertiary);
    cursor: pointer;
    transition: all 0.2s ease;
    font-weight: 500;

    &.active {
        background: var(--el-color-primary);
        color: #fff;
    }
}

.trend-charts {
    display: flex;
    gap: 28px;
}

.trend-chart-wrapper {
    flex: 1;
}

.trend-label {
    font-size: 13px;
    color: var(--color-text-tertiary);
    margin-bottom: 12px;
    font-weight: 500;
}

.trend-chart {
    width: 100%;
    height: 260px;
}

// ===== Responsive =====
@media (max-width: 1024px) {
    .middle-section {
        flex-direction: column;
    }
}

@media (max-width: 768px) {
    .kpi-row {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .kpi-card {
        padding: 20px 16px;
    }

    .kpi-value {
        font-size: 28px;
    }

    .trend-charts {
        flex-direction: column;
    }

    .quick-nav-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .resource-content {
        flex-direction: column;
    }
}
</style>
