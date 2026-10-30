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

import { getActiveRanking, getDashboardStats, getRecentActivities } from '@/api/dashboard'
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
    indigo: { from: '#4f6bff', to: '#7a8dff', dark: '#3f58e6' },
    teal: { from: '#14b8a6', to: '#2dd4bf', dark: '#0f766e' },
    amber: { from: '#FF9F40', to: '#FFC078', dark: '#E0820F' },
    coral: { from: '#FF6B81', to: '#FF95A8', dark: '#E04055' },
    purple: { from: '#9B59B6', to: '#BB77D0' },
    blue: { from: '#4C84FF', to: '#6C9FFF' },
    green: { from: '#52c41a' },
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
              tooltipBorder: '#eef1f6',
              tooltipText: '#334155',
              axisLine: '#e2e8f0',
              splitLine: '#eef1f6',
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
    {
        label: 'dashboard.quickNavItems.userManage',
        icon: User,
        route: '/user/list',
        gradient: grad(P.indigo)
    },
    {
        label: 'dashboard.quickNavItems.roleManage',
        icon: UserFilled,
        route: '/system/role',
        gradient: grad(P.teal)
    },
    {
        label: 'dashboard.quickNavItems.menuManage',
        icon: MenuIcon,
        route: '/system/menu',
        gradient: grad(P.amber)
    },
    {
        label: 'dashboard.quickNavItems.systemConfig',
        icon: Setting,
        route: '/system/config',
        gradient: grad(P.teal)
    },
    {
        label: 'dashboard.quickNavItems.loginLog',
        icon: Document,
        route: '/system/log/login',
        gradient: grad(P.coral)
    },
    {
        label: 'dashboard.quickNavItems.article',
        icon: Document,
        route: '/content/article',
        gradient: grad(P.blue)
    },
    {
        label: 'dashboard.quickNavItems.announcement',
        icon: Monitor,
        route: '/content/announcement',
        gradient: grad(P.amber)
    }
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
    <div class="content dashboard">
        <div class="kpi-row">
            <div
                v-for="(card, i) in kpiCards"
                :key="card.label"
                class="kpi-card"
                :class="'kpi-' + ['primary', 'purple', 'orange', 'sky'][i]"
            >
                <span v-if="i === 0" class="kpi-watermark" aria-hidden="true">#</span>
                <div class="kpi-icon">
                    <el-icon><component :is="card.icon" /></el-icon>
                </div>
                <div class="kpi-value">{{ card.value?.toLocaleString() }}</div>
                <div class="kpi-label">{{ card.label }}</div>
                <div
                    v-if="card.trend"
                    class="kpi-trend"
                    :class="{ up: card.trend.type === 'up', down: card.trend.type === 'down' }"
                >
                    <span
                        >{{ card.trend.type === 'up' ? '↑' : '↓' }} {{ card.trend.value
                        }}{{ card.trend.unit === 'percent' ? '%' : '' }}</span
                    >
                    <span class="kpi-comparison">{{ t('dashboard.comparedLastWeek') }}</span>
                </div>
            </div>
        </div>
        <div class="row-3">
            <section class="card activity-card">
                <div class="card-head">
                    <span class="card-title">{{ t('dashboard.recentActivities') }}</span
                    ><button class="card-link" @click="navigateTo('/system/log/operation')">
                        {{ t('dashboard.viewMore') }}
                    </button>
                </div>
                <div class="card-body activity-list">
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
                                <span class="activity-user">{{ item.username }}</span
                                ><span class="activity-desc">{{ item.description }}</span>
                            </div>
                            <span class="activity-time">{{ item.relative_time }}</span>
                        </div>
                    </div>
                    <div v-if="!activities.length" class="activity-empty">
                        <el-icon><Document /></el-icon><span>暂无动态</span>
                    </div>
                </div>
            </section>
            <section class="card trend-card">
                <div class="card-head">
                    <span class="card-title">{{ t('dashboard.loginTrend') }}</span>
                    <div class="segment-btns">
                        <button
                            v-for="days in [7, 30]"
                            :key="days"
                            class="seg-btn"
                            :class="{ active: trendDays === days }"
                            @click="switchTrendDays(days)"
                        >
                            {{
                                t(
                                    days === 7
                                        ? 'dashboard.trendPeriod.week'
                                        : 'dashboard.trendPeriod.month'
                                )
                            }}
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <v-chart class="line-chart" :option="loginTrendOption" autoresize />
                </div>
            </section>
            <section class="card ranking-card">
                <div class="card-head">
                    <span class="card-title">{{ t('dashboard.activeRanking') }}</span>
                    <div class="segment-btns">
                        <button
                            v-for="p in ['day', 'week', 'month'] as const"
                            :key="p"
                            class="seg-btn"
                            :class="{ active: rankingPeriod === p }"
                            @click="switchRankingPeriod(p)"
                        >
                            {{ t(`dashboard.rankPeriod.${p}`) }}
                        </button>
                    </div>
                </div>
                <div class="card-body ranking-list">
                    <div
                        v-for="item in rankingList.slice(0, 6)"
                        :key="item.rank"
                        class="ranking-item"
                        :class="`rank-${item.rank}`"
                    >
                        <div class="rank-badge">
                            <span v-if="item.rank <= 3" class="rank-medal">{{
                                ['🥇', '🥈', '🥉'][item.rank - 1]
                            }}</span
                            ><span v-else class="rank-num">{{ item.rank }}</span>
                        </div>
                        <span class="rank-name">{{ item.username }}</span
                        ><span class="rank-count"
                            >{{ item.count }}
                            <span class="rank-unit">{{ t('dashboard.times') }}</span></span
                        >
                    </div>
                    <div v-if="!rankingList.length" class="activity-empty">
                        <el-icon><Trophy /></el-icon><span>暂无排行</span>
                    </div>
                </div>
            </section>
        </div>
        <div class="row-2">
            <section class="card register-card">
                <div class="card-head">
                    <span class="card-title">{{ t('dashboard.registerTrend') }}</span
                    ><span class="card-meta">{{
                        t(
                            trendDays === 7
                                ? 'dashboard.trendPeriod.week'
                                : 'dashboard.trendPeriod.month'
                        )
                    }}</span>
                </div>
                <div class="card-body">
                    <v-chart class="bar-chart" :option="registerTrendOption" autoresize />
                </div>
            </section>
            <section class="card quicknav-card">
                <div class="card-head">
                    <span class="card-title">{{ t('dashboard.quickNav') }}</span>
                </div>
                <div class="card-body quick-nav-grid">
                    <button
                        v-for="item in quickNavItems"
                        :key="item.route"
                        class="nav-item"
                        @click="navigateTo(item.route)"
                    >
                        <div class="nav-icon" :style="{ background: item.gradient }">
                            <el-icon :size="20" color="#fff"><component :is="item.icon" /></el-icon>
                        </div>
                        <span class="nav-label">{{ t(item.label) }}</span>
                    </button>
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped lang="scss">
// ═══════ Layout ═══════
.content {
    padding: 0;
    min-height: 100%;
}

// ═══════ KPI Row ═══════
.kpi-row {
    display: grid;
    grid-template-columns: 1.2fr 1fr 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}

.kpi-card {
    position: relative;
    border-radius: var(--r-lg);
    padding: 22px 24px;
    background: var(--card-bg);
    border: 1px solid var(--ink-100);
    box-shadow: var(--shadow-sm);
    min-height: 128px;
    overflow: hidden;
}

.kpi-watermark {
    position: absolute;
    right: -8px;
    bottom: -18px;
    font-size: 130px;
    font-weight: 800;
    opacity: 0.08;
    color: #fff;
    line-height: 1;
    pointer-events: none;
    font-family: var(--font-num);
}

.kpi-value {
    font-size: 30px;
    font-weight: 700;
    font-family: var(--font-num);
    color: var(--ink-900);
    margin-bottom: 4px;
}

.kpi-label {
    font-size: 13px;
    color: var(--ink-500);
}

.kpi-trend {
    font-size: 12.5px;
    margin-top: 8px;

    &.up {
        color: var(--success);
    }
    &.down {
        color: var(--danger);
    }
}

.kpi-icon {
    position: absolute;
    right: 18px;
    top: 18px;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

// Primary KPI — gradient
.kpi-primary {
    background: linear-gradient(135deg, #4f6bff 0%, #6d7dff 60%, #8b5cf6 100%);
    color: #fff;
    border: none;
    box-shadow: 0 10px 30px var(--brand-shadow);

    .kpi-value,
    .kpi-label {
        color: #fff;
    }
    .kpi-label {
        opacity: 0.85;
    }
    .kpi-trend {
        color: rgba(255, 255, 255, 0.75) !important;
    }
    .kpi-icon {
        background: rgba(255, 255, 255, 0.2);
        color: #fff;
    }
}

.kpi-purple .kpi-icon {
    background: var(--purple-50);
    color: var(--purple-500);
}

.kpi-orange .kpi-icon {
    background: var(--amber-50);
    color: var(--amber-500);
}

.kpi-sky .kpi-icon {
    background: var(--sky-50);
    color: var(--sky-500);
}

// Non-primary trend colors
.kpi-purple,
.kpi-orange,
.kpi-sky {
    .kpi-trend.up {
        color: var(--success);
    }
    .kpi-trend.down {
        color: var(--danger);
    }
}

// ═══════ Card Component ═══════
.card {
    background: var(--card-bg);
    border-radius: var(--r-lg);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--ink-100);
    overflow: hidden;
}

.card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px 0;
}

.card-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--ink-900);
    display: flex;
    align-items: center;
    gap: 8px;

    &::before {
        content: '';
        width: 3px;
        height: 14px;
        border-radius: 2px;
        background: var(--brand-500);
        flex-shrink: 0;
    }
}

.card-body {
    padding: 16px 20px 20px;
}

.card-meta {
    font-size: 12px;
    color: var(--ink-400);
}

// ═══════ Row-3 ═══════
.row-3 {
    display: grid;
    grid-template-columns: 1fr 1.3fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}

// ─── Order Pills ───
.order-pills {
    display: flex;
    gap: 20px;
    margin-bottom: 12px;
}

.pill {
    display: flex;
    align-items: center;
    gap: 8px;
}

.pill-bar {
    width: 4px;
    height: 28px;
    border-radius: 2px;

    &.brand {
        background: var(--brand-500);
    }
    &.rose {
        background: var(--rose-500);
    }
}

.pill-label {
    font-size: 12px;
    color: var(--ink-400);
}

.pill-value {
    font-size: 18px;
    font-weight: 700;
    color: var(--ink-900);
    font-family: var(--font-num);
}

// ─── Donut ───
.donut-chart {
    width: 100%;
    height: 200px;
}

.donut-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 12px 24px;
    margin-top: 4px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 2px;
    flex-shrink: 0;
}

.legend-name {
    font-size: 12.5px;
    color: var(--ink-500);
}

.legend-pct {
    font-size: 12.5px;
    color: var(--ink-800);
    font-weight: 600;
    font-family: var(--font-num);
}

// ─── Segment Buttons ───
.segment-btns {
    display: flex;
    gap: 0;
    border: 1px solid var(--ink-200);
    border-radius: 6px;
    overflow: hidden;
}

.seg-btn {
    font-size: 12px;
    padding: 4px 14px;
    background: var(--card-bg);
    color: var(--ink-500);
    border: none;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.15s;

    &.active {
        background: var(--brand-500);
        color: #fff;
    }

    &:hover:not(.active) {
        background: var(--ink-50);
    }
}

// ─── Line Chart ───
.trend-card {
    display: flex;
    flex-direction: column;

    > .card-body {
        display: flex;
        flex: 1;
        min-height: 0;
    }

    .line-chart {
        flex: 1;
        width: 100%;
        height: auto;
        min-height: 280px;
    }
}

// ─── Feedback ───
.feedback-body {
    text-align: center;
}

.feedback-score {
    font-size: 48px;
    font-weight: 800;
    color: var(--ink-900);
    font-family: var(--font-num);
    line-height: 1.1;
}

.feedback-stars {
    font-size: 22px;
    color: var(--amber-500);
    margin: 4px 0 2px;
    letter-spacing: 2px;
}

.feedback-count {
    font-size: 12px;
    color: var(--ink-400);
    margin-bottom: 20px;
}

.rating-bars {
    text-align: left;
}

.rating-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;

    &:last-child {
        margin-bottom: 0;
    }
}

.rating-label {
    font-size: 12px;
    color: var(--ink-500);
    width: 24px;
    flex-shrink: 0;
    text-align: right;
}

.rating-track {
    flex: 1;
    height: 6px;
    background: var(--ink-100);
    border-radius: 3px;
    position: relative;
}

.rating-fill {
    height: 100%;
    border-radius: 3px;
    position: relative;
    transition: width 0.4s ease;
}

.rating-dot {
    position: absolute;
    right: -4px;
    top: 50%;
    transform: translateY(-50%);
    width: 8px;
    height: 8px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
}

.rating-pct {
    font-size: 12px;
    color: var(--ink-500);
    width: 32px;
    flex-shrink: 0;
    font-family: var(--font-num);
}

// ═══════ Row-2 ═══════
.row-2 {
    display: grid;
    grid-template-columns: 1.7fr 1fr;
    gap: 16px;
}

// ─── Bar Chart ───
.bar-body {
    position: relative;
}

.bar-chart {
    width: 100%;
    height: 300px;
}

.bar-legend {
    display: flex;
    gap: 16px;
}

.bar-legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--ink-500);
}

.bar-legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 2px;

    &.brand-dot {
        background: var(--brand-500);
    }
    &.expense-dot {
        background: var(--brand-100);
    }
}

// ─── Ticket (工单情况) ───
.ticket-body {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.ticket-total {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px;
    background: var(--brand-50);
    border-radius: 10px;
}

.ticket-total-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: var(--brand-500);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ticket-total-info {
    display: flex;
    flex-direction: column;
}

.ticket-total-label {
    font-size: 12px;
    color: var(--ink-400);
}

.ticket-total-value {
    font-size: 24px;
    font-weight: 700;
    color: var(--ink-900);
    font-family: var(--font-num);
}

.ticket-cells {
    display: flex;
    gap: 12px;
}

.ticket-cell {
    flex: 1;
    background: var(--ink-50);
    border-radius: 10px;
    padding: 16px;
}

.ticket-cell-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--ink-800);
    margin-bottom: 10px;
}

.ticket-cell-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;

    &:last-child {
        margin-bottom: 0;
    }
}

.ticket-cell-label {
    font-size: 12px;
    color: var(--ink-400);
}

.ticket-cell-value {
    font-size: 16px;
    font-weight: 700;
    color: var(--ink-900);
    font-family: var(--font-num);
}

// ═══════ Responsive ═══════
@media (max-width: 1200px) {
    .row-3 {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 1024px) {
    .row-3 {
        grid-template-columns: 1fr;
    }
    .row-2 {
        grid-template-columns: 1fr;
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
        font-size: 24px;
    }
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
        background: linear-gradient(90deg, rgba(255, 215, 0, 0.1), transparent 70%);
    }
    &.rank-2 {
        background: linear-gradient(90deg, rgba(192, 192, 192, 0.1), transparent 70%);
    }
    &.rank-3 {
        background: linear-gradient(90deg, rgba(205, 127, 50, 0.1), transparent 70%);
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

.card-link {
    border: 0;
    background: transparent;
    padding: 0;
    color: var(--brand-500);
    font-size: 12px;
    cursor: pointer;
}
.kpi-comparison {
    margin-left: 6px;
    font-size: 11.5px;
    opacity: 0.8;
}
.kpi-icon {
    font-size: 20px;
}
.activity-body {
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
}
.activity-text {
    width: 100%;
}
.card-head {
    gap: 10px;
    flex-wrap: wrap;
}
.card-body {
    min-width: 0;
}
.quick-nav-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
.nav-item {
    border: 0;
    background: transparent;
    font: inherit;
}
.row-3 > *,
.row-2 > *,
.kpi-row > * {
    min-width: 0;
}
</style>
