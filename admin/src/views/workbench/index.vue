<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import '@/utils/echart'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { getDashboardStats } from '@/api/dashboard'
import type { DashboardStats } from '@/types/api'
import VChart from 'vue-echarts'
import {
    User, TrendCharts, Plus, Key,
    UserFilled, Menu as MenuIcon, Lock, Setting, Document, Monitor
} from '@element-plus/icons-vue'

const router = useRouter()
const { t } = useI18n()
// State
const stats = ref<DashboardStats | null>(null)
const trendDays = ref(7)

// Data loading
const loadStats = async () => {
    const res = await getDashboardStats(trendDays.value)
    stats.value = res.data
}

const switchTrendDays = (days: number) => {
    trendDays.value = days
    loadStats()
}

onMounted(() => {
    loadStats()
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

// Quick nav items
const quickNavItems = [
    { label: 'dashboard.quickNavItems.userManage', icon: User, route: '/user/list', gradient: 'linear-gradient(135deg, #4C84FF, #6C9FFF)' },
    { label: 'dashboard.quickNavItems.roleManage', icon: UserFilled, route: '/system/role', gradient: 'linear-gradient(135deg, #36CFC9, #5CE0DB)' },
    { label: 'dashboard.quickNavItems.menuManage', icon: MenuIcon, route: '/system/menu', gradient: 'linear-gradient(135deg, #F5A623, #F7C164)' },
    { label: 'dashboard.quickNavItems.permManage', icon: Lock, route: '/system/permission', gradient: 'linear-gradient(135deg, #9B59B6, #BB77D0)' },
    { label: 'dashboard.quickNavItems.systemConfig', icon: Setting, route: '/system/config', gradient: 'linear-gradient(135deg, #36CFC9, #5CE0DB)' },
    { label: 'dashboard.quickNavItems.loginLog', icon: Document, route: '/system/admin_login_log', gradient: 'linear-gradient(135deg, #FF6B6B, #FF8E8E)' },
    { label: 'dashboard.quickNavItems.article', icon: Document, route: '/content/article', gradient: 'linear-gradient(135deg, #4C84FF, #6C9FFF)' },
    { label: 'dashboard.quickNavItems.announcement', icon: Monitor, route: '/content/announcement', gradient: 'linear-gradient(135deg, #F5A623, #F7C164)' },
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
            radius: ['50%', '72%'],
            center: ['50%', '50%'],
            avoidLabelOverlap: false,
            label: {
                show: true,
                position: 'center',
                formatter: () => total.toString(),
                fontSize: 28,
                fontWeight: 700,
                color: '#4C84FF',
            },
            emphasis: {
                label: { show: true, fontSize: 28, fontWeight: 700 },
            },
            itemStyle: {
                borderRadius: 4,
                borderColor: '#fff',
                borderWidth: 2,
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
    tooltip: {
        trigger: 'axis',
        backgroundColor: 'rgba(255,255,255,0.96)',
        borderColor: '#eee',
        textStyle: { color: '#333', fontSize: 13 },
    },
    grid: { top: 16, right: 20, bottom: 30, left: 48 },
    xAxis: {
        type: 'category',
        data: data.map(i => i.date),
        axisLine: { lineStyle: { color: '#e5e7eb' } },
        axisLabel: { color: '#94a3b8', fontSize: 12 },
        axisTick: { show: false },
    },
    yAxis: {
        type: 'value',
        minInterval: 1,
        splitLine: { lineStyle: { type: 'dashed', color: '#f0f0f0' } },
        axisLabel: { color: '#94a3b8', fontSize: 12 },
    },
    series: [{
        type: 'line',
        data: data.map(i => i.count),
        smooth: true,
        symbol: 'circle',
        symbolSize: 7,
        lineStyle: { color, width: 3 },
        itemStyle: { color, borderWidth: 2, borderColor: '#fff' },
        areaStyle: {
            color: {
                type: 'linear', x: 0, y: 0, x2: 0, y2: 1,
                colorStops: [
                    { offset: 0, color: color + '40' },
                    { offset: 1, color: color + '05' },
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

  </div>
</template>

<style scoped lang="scss">
.dashboard {
  padding: 0;
  background: #f0f5ff;
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
  background: rgba(255, 255, 255, 0.88);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 255, 255, 0.7);
  border-radius: 8px;
  padding: 24px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
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
  color: #1e293b;
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
  border-bottom: 1px solid #f5f5f5;

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
  color: #64748b;
  flex: 1;
}

.legend-value {
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
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
    background: #f0f5ff;
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
  color: #1e293b;
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
  background: #f0f0f0;
  color: #666;
  cursor: pointer;
  transition: all 0.2s ease;
  font-weight: 500;

  &.active {
    background: #4C84FF;
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
  color: #94a3b8;
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
