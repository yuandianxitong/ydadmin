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
    Trophy,
    User,
    UserFilled
} from '@element-plus/icons-vue'
import { computed, onMounted, ref } from 'vue'
import VChart from 'vue-echarts'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import {
    getActiveRanking,
    getDashboardStats,
    getRecentActivities
} from '@/api/dashboard'
import useSettingStore from '@/store/modules/settings.store'
import type { ActivityItem, DashboardStats, RankingItem } from '@/types/dashboard'

const router = useRouter()
const { t } = useI18n()
const settingStore = useSettingStore()

// Dark mode detection for ECharts
const isDark = computed(() => {
    if (settingStore.themeMode === 'dark') return true
    if (settingStore.themeMode === 'light') return false
    return document.documentElement.classList.contains('dark')
})

// 仪表盘调色板（KPI、快捷导航、图表共用）
const P = {
    indigo: { from: '#6A6BFF', to: '#8E8FFE', dark: '#5557E0' },
    teal:   { from: '#34D8C5', to: '#5BE9D8', dark: '#1FB8A6' },
    amber:  { from: '#FF9F40', to: '#FFC078', dark: '#E0820F' },
    coral:  { from: '#FF6B81', to: '#FF95A8', dark: '#E04055' },
    purple: { from: '#9B59B6', to: '#BB77D0' },
    blue:   { from: '#4C84FF', to: '#6C9FFF' },
    green:  { from: '#52c41a' },
    yellow: { from: '#faad14' }
}
const grad = (c: { from: string; to?: string }, dir = '135deg') =>
    `linear-gradient(${dir}, ${c.from}, ${c.to || c.from})`

const chartColors = computed(() =>
    isDark.value
        ? {
              tooltipBg: 'rgba(30,31,34,0.96)',
              tooltipBorder: '#2e2f33',
              tooltipText: '#e5e7eb',
              axisLine: '#2e2f33',
              splitLine: '#252629',
              axisLabel: '#6b7280'
          }
        : {
              tooltipBg: 'rgba(255,255,255,0.96)',
              tooltipBorder: '#eee',
              tooltipText: '#333',
              axisLine: '#e5e7eb',
              splitLine: '#f0f0f0',
              axisLabel: '#94a3b8'
          }
)

// State
const stats = ref<DashboardStats | null>(null)
const activities = ref<ActivityItem[]>([])
const rankingList = ref<RankingItem[]>([])
const rankingPeriod = ref<'day' | 'week' | 'month'>('day')
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

const loadActivities = async () => {
    try {
        const res = await getRecentActivities()
        activities.value = res.data || []
    } catch (e) {
        console.error('加载动态失败:', e)
    }
}

const loadRanking = async () => {
    try {
        const res = await getActiveRanking(rankingPeriod.value)
        rankingList.value = res.data?.list || []
    } catch (e) {
        console.error('加载排行失败:', e)
    }
}

const switchTrendDays = (days: number) => {
    trendDays.value = days
    loadStats()
}

const switchRankingPeriod = (period: 'day' | 'week' | 'month') => {
    rankingPeriod.value = period
    loadRanking()
}

onMounted(() => {
    loadStats()
    loadActivities()
    loadRanking()
})

// KPI Cards config — 4 heroic gradient cards
const kpiCards = computed(() => {
    if (!stats.value) return []
    const s = stats.value
    return [
        {
            label: t('dashboard.totalUsers'),
            value: s.totalUsers,
            trend: s.trends.totalUsers,
            palette: P.indigo,
            icon: User
        },
        {
            label: t('dashboard.activeUsers'),
            value: s.activeUsers,
            trend: s.trends.activeUsers,
            palette: P.teal,
            icon: TrendCharts
        },
        {
            label: t('dashboard.todayNew'),
            value: s.todayNewUsers,
            trend: s.trends.todayNewUsers,
            palette: P.amber,
            icon: Plus
        },
        {
            label: t('dashboard.todayLogin'),
            value: s.todayLoginCount,
            trend: s.trends.todayLoginCount,
            palette: P.coral,
            icon: Key
        }
    ]
})

// Quick nav items
const quickNavItems = [
    { label: 'dashboard.quickNavItems.userManage',    icon: User,       route: '/user/list',                  gradient: grad(P.indigo) },
    { label: 'dashboard.quickNavItems.roleManage',    icon: UserFilled, route: '/system/role',                gradient: grad(P.teal)   },
    { label: 'dashboard.quickNavItems.menuManage',    icon: MenuIcon,   route: '/system/menu',                gradient: grad(P.amber)  },
    { label: 'dashboard.quickNavItems.permManage',    icon: Lock,       route: '/system/permission',          gradient: grad(P.purple) },
    { label: 'dashboard.quickNavItems.systemConfig',  icon: Setting,    route: '/system/config',              gradient: grad(P.teal)   },
    { label: 'dashboard.quickNavItems.loginLog',      icon: Document,   route: '/system/admin_login_log',     gradient: grad(P.coral)  },
    { label: 'dashboard.quickNavItems.article',       icon: Document,   route: '/content/article',            gradient: grad(P.blue)   },
    { label: 'dashboard.quickNavItems.announcement',  icon: Monitor,    route: '/content/announcement',       gradient: grad(P.amber)  }
]

// Activity icon and color mapping
const activityMeta = (type: ActivityItem['type']) => {
    switch (type) {
        case 'login_success':
            return { icon: Key, color: P.teal.from, bg: 'rgba(52, 216, 197, 0.12)' }
        case 'login_failed':
            return { icon: Lock, color: P.coral.from, bg: 'rgba(255, 107, 129, 0.12)' }
        default:
            return { icon: Setting, color: P.indigo.from, bg: 'rgba(106, 107, 255, 0.12)' }
    }
}

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
    return buildTrendOption(stats.value.registerTrend, P.indigo.from)
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
                :style="{ background: grad(card.palette, '120deg') }"
            >
                <!-- Decorative SVG curves -->
                <svg
                    class="kpi-deco"
                    viewBox="0 0 240 200"
                    preserveAspectRatio="xMaxYMid slice"
                    aria-hidden="true"
                >
                    <defs>
                        <linearGradient :id="`kpi-grad-${i}`" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stop-color="#fff" stop-opacity="0.32" />
                            <stop offset="100%" stop-color="#fff" stop-opacity="0" />
                        </linearGradient>
                    </defs>
                    <circle cx="220" cy="40" r="80" :fill="`url(#kpi-grad-${i})`" />
                    <circle cx="180" cy="160" r="60" fill="#fff" fill-opacity="0.10" />
                    <path
                        d="M120 200 Q180 120 240 140 L240 200 Z"
                        fill="#fff"
                        fill-opacity="0.08"
                    />
                    <path
                        d="M160 -20 Q210 60 240 30"
                        stroke="#fff"
                        stroke-opacity="0.18"
                        stroke-width="1.5"
                        fill="none"
                    />
                </svg>

                <div class="kpi-head">
                    <span class="kpi-title">{{ card.label }}</span>
                    <el-icon class="kpi-badge"><component :is="card.icon" /></el-icon>
                </div>

                <div class="kpi-value">{{ card.value?.toLocaleString() }}</div>

                <div v-if="card.trend" class="kpi-trend">
                    <span class="kpi-trend-pill" :class="`is-${card.trend.type}`">
                        <span class="kpi-trend-arrow">{{
                            card.trend.type === 'up' ? '↑' : '↓'
                        }}</span>
                        {{ card.trend.value
                        }}{{ card.trend.unit === 'percent' ? '%' : '' }}
                    </span>
                    <span class="kpi-trend-label">{{ t('dashboard.comparedLastWeek') }}</span>
                </div>
            </div>
        </div>

        <!-- Middle Section: Recent Activity + Active Ranking -->
        <div class="middle-section">
            <!-- Recent Activities -->
            <div class="soft-card activity-card">
                <div class="card-header">
                    <span class="card-title">{{ t('dashboard.recentActivities') }}</span>
                    <span class="card-link" @click="navigateTo('/system/admin_operation_log')">{{
                        t('dashboard.viewMore')
                    }}</span>
                </div>
                <div class="activity-list">
                    <div
                        v-for="(item, idx) in activities.slice(0, 6)"
                        :key="idx"
                        class="activity-item"
                    >
                        <div
                            class="activity-icon"
                            :style="{
                                background: activityMeta(item.type).bg,
                                color: activityMeta(item.type).color
                            }"
                        >
                            <el-icon><component :is="activityMeta(item.type).icon" /></el-icon>
                        </div>
                        <div class="activity-body">
                            <div class="activity-text">
                                <span class="activity-user">{{ item.username }}</span>
                                <span class="activity-desc">{{ item.description }}</span>
                            </div>
                            <span class="activity-time">{{ item.relative_time }}</span>
                        </div>
                    </div>
                    <div v-if="!activities.length" class="activity-empty">
                        <el-icon><Document /></el-icon>
                        <span>暂无动态</span>
                    </div>
                </div>
            </div>

            <!-- Active Ranking -->
            <div class="soft-card ranking-card">
                <div class="card-header">
                    <span class="card-title">
                        <el-icon class="title-icon"><Trophy /></el-icon>
                        {{ t('dashboard.activeRanking') }}
                    </span>
                    <div class="period-tabs">
                        <span
                            v-for="p in (['day', 'week', 'month'] as const)"
                            :key="p"
                            class="period-tab"
                            :class="{ active: rankingPeriod === p }"
                            @click="switchRankingPeriod(p)"
                        >{{ t(`dashboard.rankPeriod.${p}`) }}</span>
                    </div>
                </div>
                <div class="ranking-list">
                    <div
                        v-for="item in rankingList.slice(0, 6)"
                        :key="item.rank"
                        class="ranking-item"
                        :class="`rank-${item.rank}`"
                    >
                        <div class="rank-badge">
                            <span v-if="item.rank <= 3" class="rank-medal">{{
                                ['🥇', '🥈', '🥉'][item.rank - 1]
                            }}</span>
                            <span v-else class="rank-num">{{ item.rank }}</span>
                        </div>
                        <span class="rank-name">{{ item.username }}</span>
                        <span class="rank-count">
                            {{ item.count }}
                            <span class="rank-unit">{{ t('dashboard.times') }}</span>
                        </span>
                    </div>
                    <div v-if="!rankingList.length" class="activity-empty">
                        <el-icon><Trophy /></el-icon>
                        <span>暂无排行</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Nav (full width) -->
        <div class="soft-card quicknav-card">
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

        <!-- Trend Overview -->
        <div class="soft-card trend-card">
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
                    <div class="trend-label">
                        <span class="trend-dot" :style="{ background: P.indigo.from }"></span>
                        {{ t('dashboard.registerTrend') }}
                    </div>
                    <v-chart class="trend-chart" :option="registerTrendOption" autoresize />
                </div>
                <div class="trend-chart-wrapper">
                    <div class="trend-label">
                        <span class="trend-dot" :style="{ background: P.teal.from }"></span>
                        {{ t('dashboard.loginTrend') }}
                    </div>
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
    gap: 18px;
    margin-bottom: 18px;
}

.kpi-card {
    position: relative;
    border-radius: 6px;
    padding: 22px 24px 22px;
    color: #fff;
    overflow: hidden;
    transition: transform 0.3s var(--motion-easing), box-shadow 0.3s var(--motion-easing);
    cursor: default;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
    display: flex;
    flex-direction: column;
    gap: 14px;
    min-height: 138px;

    &:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 32px rgba(0, 0, 0, 0.16);
    }
}

.kpi-deco {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
}

.kpi-head {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.kpi-title {
    font-size: 14px;
    font-weight: 500;
    letter-spacing: 0.3px;
    opacity: 0.95;
}

.kpi-badge {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.20);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.kpi-value {
    position: relative;
    z-index: 1;
    font-size: 36px;
    font-weight: 700;
    letter-spacing: -0.6px;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
}

.kpi-trend {
    position: relative;
    z-index: 1;
    margin-top: auto;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
}

.kpi-trend-pill {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    padding: 2px 8px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.22);
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.kpi-trend-arrow {
    font-weight: 700;
}

.kpi-trend-label {
    opacity: 0.78;
}

// ===== Soft Card =====
.soft-card {
    background: var(--color-surface);
    border: none;
    border-radius: 6px;
    padding: 22px 22px 20px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03), 0 6px 18px rgba(0, 0, 0, 0.05);
    transition: box-shadow 0.25s var(--motion-easing);

    &:hover {
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04), 0 10px 26px rgba(0, 0, 0, 0.07);
    }
}

html.dark .soft-card {
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.3), 0 6px 18px rgba(0, 0, 0, 0.35);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
}

.card-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--color-text-primary);
    display: inline-flex;
    align-items: center;
    gap: 6px;

    .title-icon {
        color: var(--el-color-primary);
        font-size: 16px;
    }
}

.card-link {
    font-size: 12px;
    color: var(--color-text-tertiary);
    cursor: pointer;
    transition: color var(--motion-duration-fast);

    &:hover {
        color: var(--el-color-primary);
    }
}

// ===== Middle Section =====
.middle-section {
    display: grid;
    grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr);
    gap: 18px;
    margin-bottom: 18px;
}

// ===== Activity =====
.activity-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px dashed var(--color-divider);
    transition: background var(--motion-duration-fast);

    &:last-child {
        border-bottom: none;
    }
}

.activity-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

.activity-body {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-width: 0;
}

.activity-text {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: baseline;
    gap: 8px;
    overflow: hidden;

    .activity-user {
        font-size: 13px;
        font-weight: 600;
        color: var(--color-text-primary);
        flex-shrink: 0;
    }

    .activity-desc {
        font-size: 13px;
        color: var(--color-text-tertiary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
}

.activity-time {
    font-size: 12px;
    color: var(--color-text-disabled);
    flex-shrink: 0;
    font-variant-numeric: tabular-nums;
}

.activity-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 40px 0;
    color: var(--color-text-disabled);
    font-size: 13px;
    .el-icon {
        font-size: 28px;
    }
}

// ===== Ranking =====
.ranking-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.ranking-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 6px;
    transition: background var(--motion-duration-fast);

    &:hover {
        background: var(--el-fill-color-light);
    }

    &.rank-1 {
        background: linear-gradient(90deg, rgba(255, 215, 0, 0.10), transparent 70%);
    }
    &.rank-2 {
        background: linear-gradient(90deg, rgba(192, 192, 192, 0.10), transparent 70%);
    }
    &.rank-3 {
        background: linear-gradient(90deg, rgba(205, 127, 50, 0.10), transparent 70%);
    }
}

.rank-badge {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;

    .rank-medal {
        font-size: 18px;
    }

    .rank-num {
        width: 22px;
        height: 22px;
        line-height: 22px;
        text-align: center;
        font-size: 12px;
        font-weight: 600;
        color: var(--color-text-tertiary);
        background: var(--el-fill-color);
        border-radius: 50%;
    }
}

.rank-name {
    flex: 1;
    font-size: 13px;
    color: var(--color-text-primary);
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.rank-count {
    font-size: 14px;
    font-weight: 700;
    color: var(--color-text-primary);
    font-variant-numeric: tabular-nums;

    .rank-unit {
        font-size: 11px;
        font-weight: 400;
        color: var(--color-text-tertiary);
        margin-left: 2px;
    }
}

// ===== Quick Nav =====
.quicknav-card {
    margin-bottom: 18px;
}

.quick-nav-grid {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: 8px;
}

.nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 16px 8px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.25s var(--motion-easing);

    &:hover {
        background: var(--el-fill-color-light);
        transform: translateY(-2px);
        .nav-icon {
            transform: scale(1.06);
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.14);
        }
    }
}

.nav-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.25s var(--motion-easing);
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.08);
}

.nav-label {
    font-size: 12px;
    color: var(--color-text-secondary);
    font-weight: 500;
}

// ===== Period tabs (shared) =====
.period-tabs {
    display: flex;
    gap: 4px;
    background: var(--el-fill-color-light);
    padding: 3px;
    border-radius: 999px;
}

.period-tab {
    font-size: 12px;
    padding: 4px 12px;
    border-radius: 999px;
    color: var(--color-text-tertiary);
    cursor: pointer;
    transition: all 0.2s ease;
    font-weight: 500;

    &.active {
        background: var(--color-surface);
        color: var(--el-color-primary);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
}

// ===== Trend =====
.trend-card {
    margin-bottom: 18px;
}

.trend-charts {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 28px;
}

.trend-chart-wrapper {
    flex: 1;
}

.trend-label {
    font-size: 13px;
    color: var(--color-text-secondary);
    margin-bottom: 8px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.trend-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}

.trend-chart {
    width: 100%;
    height: 240px;
}

// ===== Responsive =====
@media (max-width: 1280px) {
    .quick-nav-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 1024px) {
    .kpi-row {
        grid-template-columns: repeat(2, 1fr);
    }
    .middle-section {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .kpi-card {
        padding: 18px 18px 16px;
        min-height: auto;
    }
    .kpi-value {
        font-size: 28px;
    }
    .quick-nav-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .trend-charts {
        grid-template-columns: 1fr;
    }
}
</style>
