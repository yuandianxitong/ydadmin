<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import '@/utils/echart'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/store/modules/app.store'
import { getDashboardStats, getRecentActivities, getActiveRanking } from '@/api/dashboard'
import type { DashboardStats, ActivityItem, ActiveRanking } from '@/types/api'
import VChart from 'vue-echarts'
import {
    User, TrendCharts, Plus, Key,
    UserFilled, Menu as MenuIcon, Lock, Setting, Document,
    Monitor, Stamp, Cpu, Platform, Ticket
} from '@element-plus/icons-vue'

const router = useRouter()
const { t } = useI18n()
const appStore = useAppStore()

// State
const stats = ref<DashboardStats | null>(null)
const activities = ref<ActivityItem[]>([])
const ranking = ref<ActiveRanking | null>(null)
const rankPeriod = ref('day')
const trendDays = ref(7)

// Data loading
const loadStats = async () => {
    const res = await getDashboardStats(trendDays.value)
    stats.value = res.data
}

const loadActivities = async () => {
    const res = await getRecentActivities()
    activities.value = res.data
}

const loadRanking = async () => {
    const res = await getActiveRanking(rankPeriod.value)
    ranking.value = res.data
}

const switchRankPeriod = (period: string) => {
    rankPeriod.value = period
    loadRanking()
}

const switchTrendDays = (days: number) => {
    trendDays.value = days
    loadStats()
}

onMounted(() => {
    Promise.all([loadStats(), loadActivities(), loadRanking()])
})

// KPI Cards config
const kpiCards = computed(() => {
    if (!stats.value) return []
    const s = stats.value
    return [
        {
            label: t('dashboard.totalUsers'),
            value: s.totalUsers,
            trend: s.trends.totalUsers,
            gradient: 'linear-gradient(135deg, #4C84FF, #6C9FFF)',
            icon: User,
        },
        {
            label: t('dashboard.activeUsers'),
            value: s.activeUsers,
            trend: s.trends.activeUsers,
            gradient: 'linear-gradient(135deg, #36CFC9, #5CE0DB)',
            icon: TrendCharts,
        },
        {
            label: t('dashboard.todayNew'),
            value: s.todayNewUsers,
            trend: s.trends.todayNewUsers,
            gradient: 'linear-gradient(135deg, #F5A623, #F7C164)',
            icon: Plus,
        },
        {
            label: t('dashboard.todayLogin'),
            value: s.todayLoginCount,
            trend: s.trends.todayLoginCount,
            gradient: 'linear-gradient(135deg, #FF6B6B, #FF8E8E)',
            icon: Key,
        },
    ]
})

// Sub stats
const subStats = computed(() => {
    if (!stats.value) return []
    const s = stats.value
    return [
        { label: t('dashboard.newAdmins'), value: s.newAdmins },
        { label: t('dashboard.newRoles'), value: s.newRoles },
        { label: t('dashboard.newMenus'), value: s.newMenus },
        { label: t('dashboard.operationLogs'), value: s.operationLogCount },
    ]
})

// Quick nav items
const quickNavItems = [
    { label: 'dashboard.quickNavItems.userManage', icon: User, route: '/user/list', gradient: 'linear-gradient(135deg, #4C84FF, #6C9FFF)' },
    { label: 'dashboard.quickNavItems.roleManage', icon: UserFilled, route: '/system/role', gradient: 'linear-gradient(135deg, #36CFC9, #5CE0DB)' },
    { label: 'dashboard.quickNavItems.menuManage', icon: MenuIcon, route: '/system/menu', gradient: 'linear-gradient(135deg, #F5A623, #F7C164)' },
    { label: 'dashboard.quickNavItems.permManage', icon: Lock, route: '/system/permission', gradient: 'linear-gradient(135deg, #9B59B6, #BB77D0)' },
    { label: 'dashboard.quickNavItems.systemConfig', icon: Setting, route: '/system/config', gradient: 'linear-gradient(135deg, #36CFC9, #5CE0DB)' },
    { label: 'dashboard.quickNavItems.loginLog', icon: Document, route: '/system/admin_login_log', gradient: 'linear-gradient(135deg, #FF6B6B, #FF8E8E)' },
]

// Donut chart option
const donutOption = computed(() => {
    if (!stats.value) return {}
    const s = stats.value
    const total = s.totalUsers + s.roleCount + s.menuCount + s.configCount
    return {
        tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
        legend: { show: false },
        series: [{
            type: 'pie',
            radius: ['55%', '75%'],
            center: ['35%', '50%'],
            avoidLabelOverlap: false,
            label: {
                show: true,
                position: 'center',
                formatter: () => total.toString(),
                fontSize: 20,
                fontWeight: 700,
                color: '#4C84FF',
            },
            emphasis: {
                label: { show: true, fontSize: 20, fontWeight: 700 },
            },
            data: [
                { value: s.totalUsers, name: t('dashboard.users'), itemStyle: { color: '#4C84FF' } },
                { value: s.roleCount, name: t('dashboard.roles'), itemStyle: { color: '#52c41a' } },
                { value: s.menuCount, name: t('dashboard.menus'), itemStyle: { color: '#faad14' } },
                { value: s.configCount, name: t('dashboard.configs'), itemStyle: { color: '#9B59B6' } },
            ],
        }],
    }
})

// Trend chart option builder
const buildTrendOption = (data: Array<{ date: string; count: number }>, color: string) => ({
    tooltip: { trigger: 'axis' },
    grid: { top: 10, right: 16, bottom: 24, left: 40 },
    xAxis: {
        type: 'category',
        data: data.map(i => i.date),
        axisLine: { lineStyle: { color: '#e5e7eb' } },
        axisLabel: { color: '#94a3b8', fontSize: 11 },
    },
    yAxis: {
        type: 'value',
        minInterval: 1,
        splitLine: { lineStyle: { type: 'dashed', color: '#f0f0f0' } },
        axisLabel: { color: '#94a3b8', fontSize: 11 },
    },
    series: [{
        type: 'line',
        data: data.map(i => i.count),
        smooth: true,
        symbol: 'circle',
        symbolSize: 6,
        lineStyle: { color, width: 2.5 },
        itemStyle: { color },
        areaStyle: {
            color: {
                type: 'linear', x: 0, y: 0, x2: 0, y2: 1,
                colorStops: [
                    { offset: 0, color: color + '4D' },
                    { offset: 1, color: color + '00' },
                ],
            },
        },
    }],
})

const registerTrendOption = computed(() => {
    if (!stats.value?.registerTrend) return {}
    return buildTrendOption(stats.value.registerTrend, '#4C84FF')
})

const loginTrendOption = computed(() => {
    if (!stats.value?.loginTrend) return {}
    return buildTrendOption(stats.value.loginTrend, '#36CFC9')
})

// Activity dot color
const activityDotColor = (type: string) => {
    const map: Record<string, string> = {
        login_success: '#3b82f6',
        login_failed: '#ef4444',
        operation: '#22c55e',
    }
    return map[type] || '#94a3b8'
}

// Ranking badge style
const rankBadgeStyle = (rank: number) => {
    const gradients: Record<number, string> = {
        1: 'linear-gradient(135deg, #FFD700, #FFA500)',
        2: 'linear-gradient(135deg, #C0C0C0, #A0A0A0)',
        3: 'linear-gradient(135deg, #CD7F32, #B8860B)',
    }
    return {
        background: gradients[rank] || '#f0f0f0',
        color: rank <= 3 ? '#fff' : '#999',
    }
}

// System info items
const systemInfoItems = computed(() => [
    { label: t('dashboard.systemName'), value: appStore.config?.system_name || '元点Admin', icon: Monitor, bg: '#eff6ff' },
    { label: t('dashboard.systemVersion'), value: appStore.config?.system_version || 'v1.1.0', icon: Stamp, bg: '#f0fdf4' },
    { label: t('dashboard.backendFramework'), value: 'ThinkPHP 8.0', icon: Cpu, bg: '#fff7ed' },
    { label: t('dashboard.frontendFramework'), value: 'Vue 3 + TS', icon: Platform, bg: '#eff6ff' },
    { label: t('dashboard.uiLibrary'), value: 'Element Plus', icon: Setting, bg: '#faf5ff' },
    { label: t('dashboard.buildTool'), value: 'Vite', icon: Ticket, bg: '#fef2f2' },
])

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

    <!-- Sub Stats Row -->
    <div class="sub-stats-row">
      <div v-for="(item, i) in subStats" :key="i" class="sub-stat-card">
        <span class="sub-stat-label">{{ item.label }}</span>
        <span class="sub-stat-value">{{ item.value?.toLocaleString() }}</span>
      </div>
    </div>

    <!-- Middle Section: Left + Right -->
    <div class="middle-section">
      <!-- Left Column -->
      <div class="middle-left">
        <!-- Resource Overview (Donut) -->
        <div class="glass-card">
          <div class="card-header">
            <span class="card-title">{{ t('dashboard.resourceOverview') }}</span>
          </div>
          <div class="resource-content">
            <v-chart class="donut-chart" :option="donutOption" autoresize />
            <div class="resource-legend">
              <div v-if="stats" class="legend-item" v-for="item in [
                { name: t('dashboard.users'), value: stats.totalUsers, color: '#4C84FF' },
                { name: t('dashboard.roles'), value: stats.roleCount, color: '#52c41a' },
                { name: t('dashboard.menus'), value: stats.menuCount, color: '#faad14' },
                { name: t('dashboard.configs'), value: stats.configCount, color: '#9B59B6' },
              ]" :key="item.name">
                <span class="legend-dot" :style="{ background: item.color }"></span>
                <span class="legend-name">{{ item.name }}</span>
                <span class="legend-value">{{ item.value }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Activities -->
        <div class="glass-card">
          <div class="card-header">
            <span class="card-title">{{ t('dashboard.recentActivities') }}</span>
            <span class="card-link" @click="navigateTo('/system/admin_login_log')">{{ t('dashboard.viewMore') }}</span>
          </div>
          <div class="timeline">
            <div v-for="(item, i) in activities" :key="i" class="timeline-item">
              <div class="timeline-dot-wrapper">
                <span class="timeline-dot" :style="{ background: activityDotColor(item.type) }"></span>
                <span v-if="i < activities.length - 1" class="timeline-line"></span>
              </div>
              <div class="timeline-content">
                <span class="timeline-desc">{{ item.description }}</span>
                <span class="timeline-time">{{ item.relative_time }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column -->
      <div class="middle-right">
        <!-- Quick Nav -->
        <div class="glass-card">
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
                <el-icon :size="16" color="#fff"><component :is="item.icon" /></el-icon>
              </div>
              <span class="nav-label">{{ t(item.label) }}</span>
            </div>
          </div>
        </div>

        <!-- Active Ranking -->
        <div class="glass-card">
          <div class="card-header">
            <span class="card-title">{{ t('dashboard.activeRanking') }}</span>
            <div class="period-tabs">
              <span
                v-for="p in ['day', 'week', 'month']"
                :key="p"
                class="period-tab"
                :class="{ active: rankPeriod === p }"
                @click="switchRankPeriod(p)"
              >{{ t(`dashboard.rankPeriod.${p}`) }}</span>
            </div>
          </div>
          <div class="ranking-list">
            <div v-for="item in ranking?.list" :key="item.rank" class="ranking-item">
              <span class="rank-badge" :style="rankBadgeStyle(item.rank)">{{ item.rank }}</span>
              <span class="rank-name">{{ item.username }}</span>
              <span class="rank-count">{{ item.count }}{{ t('dashboard.times') }}</span>
            </div>
            <div v-if="!ranking?.list?.length" class="ranking-empty">
              {{ t('common.noData') }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Trend Overview -->
    <div class="glass-card trend-card">
      <div class="card-header">
        <span class="card-title">{{ t('dashboard.trendOverview') }}</span>
        <div class="period-tabs">
          <span class="period-tab" :class="{ active: trendDays === 7 }" @click="switchTrendDays(7)">{{ t('dashboard.trendPeriod.week') }}</span>
          <span class="period-tab" :class="{ active: trendDays === 30 }" @click="switchTrendDays(30)">{{ t('dashboard.trendPeriod.month') }}</span>
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

    <!-- System Info -->
    <div class="glass-card system-info-card">
      <div class="card-header">
        <span class="card-title">{{ t('dashboard.systemInfo') }}</span>
      </div>
      <div class="system-info-grid">
        <div v-for="(item, i) in systemInfoItems" :key="i" class="system-info-item">
          <div class="info-icon" :style="{ background: item.bg }">
            <el-icon :size="14"><component :is="item.icon" /></el-icon>
          </div>
          <div class="info-text">
            <span class="info-label">{{ item.label }}</span>
            <span class="info-value">{{ item.value }}</span>
          </div>
          <div v-if="i < systemInfoItems.length - 1" class="info-divider"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.dashboard {
  padding: 0;
  background: #f0f5ff;
  min-height: 100%;
}

.kpi-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 16px;
}

.kpi-card {
  position: relative;
  border-radius: 6px;
  padding: 20px;
  color: #fff;
  overflow: hidden;
  transition: all 0.25s ease;
  cursor: default;
  &:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
  }
}

.kpi-content {
  display: flex;
  flex-direction: column;
  position: relative;
  z-index: 1;
}

.kpi-label { font-size: 12px; opacity: 0.8; margin-bottom: 8px; }
.kpi-value { font-size: 24px; font-weight: 700; margin-bottom: 6px; }
.kpi-trend { font-size: 12px; opacity: 0.7; }

.kpi-icon {
  position: absolute;
  right: 16px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 48px;
  opacity: 0.15;
}

.sub-stats-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 16px;
}

.sub-stat-card {
  background: #fff;
  border-radius: 6px;
  padding: 14px;
  text-align: center;
  border: 1px solid #f0f0f0;
}

.sub-stat-label { display: block; font-size: 12px; color: #64748b; margin-bottom: 6px; }
.sub-stat-value { display: block; font-size: 18px; font-weight: 700; color: #1e293b; }

.glass-card {
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.6);
  border-radius: 6px;
  padding: 16px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
  margin-bottom: 16px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.card-title { font-size: 14px; font-weight: 600; color: #1e293b; }

.card-link {
  font-size: 12px;
  color: #4C84FF;
  cursor: pointer;
  &:hover { opacity: 0.8; }
}

.middle-section {
  display: flex;
  gap: 16px;
  margin-bottom: 16px;
}

.middle-left {
  flex: 3;
  display: flex;
  flex-direction: column;
  .glass-card { margin-bottom: 16px; &:last-child { margin-bottom: 0; } }
}

.middle-right {
  flex: 2;
  display: flex;
  flex-direction: column;
  .glass-card { margin-bottom: 16px; &:last-child { margin-bottom: 0; } }
}

.resource-content { display: flex; align-items: center; gap: 16px; }
.donut-chart { width: 160px; height: 160px; flex-shrink: 0; }
.resource-legend { flex: 1; }
.legend-item { display: flex; align-items: center; gap: 8px; padding: 6px 0; }
.legend-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.legend-name { font-size: 13px; color: #64748b; flex: 1; }
.legend-value { font-size: 13px; font-weight: 600; color: #1e293b; }

.timeline { padding-left: 4px; }
.timeline-item { display: flex; gap: 12px; min-height: 40px; }
.timeline-dot-wrapper { display: flex; flex-direction: column; align-items: center; width: 12px; flex-shrink: 0; padding-top: 6px; }
.timeline-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.timeline-line { width: 1px; flex: 1; background: #e5e7eb; margin-top: 4px; }
.timeline-content { flex: 1; display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 12px; }
.timeline-desc { font-size: 13px; color: #1e293b; }
.timeline-time { font-size: 11px; color: #94a3b8; flex-shrink: 0; margin-left: 12px; }

.quick-nav-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }

.nav-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 12px 4px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.25s ease;
  &:hover { background: #f8fafc; .nav-icon { transform: scale(1.05); } }
}

.nav-icon {
  width: 32px; height: 32px;
  border-radius: 6px;
  display: flex; align-items: center; justify-content: center;
  transition: transform 0.25s ease;
}

.nav-label { font-size: 12px; color: #1e293b; }

.period-tabs { display: flex; gap: 4px; }

.period-tab {
  font-size: 11px;
  padding: 2px 10px;
  border-radius: 10px;
  background: #f0f0f0;
  color: #666;
  cursor: pointer;
  transition: all 0.2s ease;
  &.active { background: #4C84FF; color: #fff; }
}

.ranking-list { display: flex; flex-direction: column; }

.ranking-item {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 0;
  border-bottom: 1px solid #f8f8f8;
  &:last-child { border-bottom: none; }
}

.rank-badge {
  width: 20px; height: 20px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 600; flex-shrink: 0;
}

.rank-name { flex: 1; font-size: 13px; color: #1e293b; }
.rank-count { font-size: 13px; font-weight: 600; color: #4C84FF; }
.ranking-empty { text-align: center; padding: 24px 0; font-size: 13px; color: #94a3b8; }

.trend-card { margin-bottom: 16px; }
.trend-charts { display: flex; gap: 24px; }
.trend-chart-wrapper { flex: 1; }
.trend-label { font-size: 12px; color: #94a3b8; margin-bottom: 8px; }
.trend-chart { width: 100%; height: 200px; }

.system-info-card { margin-bottom: 0; }
.system-info-grid { display: flex; align-items: center; }

.system-info-item {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 10px;
  position: relative;
}

.info-icon {
  width: 28px; height: 28px;
  border-radius: 6px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}

.info-text { display: flex; flex-direction: column; }
.info-label { font-size: 11px; color: #64748b; }
.info-value { font-size: 13px; font-weight: 600; color: #1e293b; }

.info-divider {
  position: absolute;
  right: 0; top: 50%; transform: translateY(-50%);
  width: 1px; height: 24px;
  background: rgba(0, 0, 0, 0.06);
}

@media (max-width: 1024px) {
  .middle-section { flex-direction: column; }
}

@media (max-width: 768px) {
  .kpi-row, .sub-stats-row { grid-template-columns: repeat(2, 1fr); }
  .trend-charts { flex-direction: column; }
  .system-info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
  .info-divider { display: none; }
  .quick-nav-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
