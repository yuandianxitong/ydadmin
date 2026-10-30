import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { expect, it, vi } from 'vitest'
import { createI18n } from 'vue-i18n'
import { createMemoryHistory, createRouter } from 'vue-router'

import zhCN from '@/locales/zh-CN'

import Workbench from './index.vue'

const mocks = vi.hoisted(() => ({ stats: vi.fn(), ranking: vi.fn(), activities: vi.fn() }))
vi.mock('vue-echarts', () => ({
    default: { name: 'VChart', props: ['option', 'autoresize'], render: () => null }
}))
vi.mock('@/api/dashboard', () => ({
    getDashboardStats: mocks.stats,
    getActiveRanking: mocks.ranking,
    getRecentActivities: mocks.activities
}))

it('renders the current server statistics in the Pro cards and keeps period controls connected', async () => {
    const trend = { type: 'up', value: 5, unit: 'percent' }
    mocks.stats.mockResolvedValue({
        data: {
            totalUsers: 12345,
            activeUsers: 120,
            todayNewUsers: 8,
            todayLoginCount: 42,
            trends: {
                totalUsers: trend,
                activeUsers: trend,
                todayNewUsers: trend,
                todayLoginCount: trend
            },
            loginTrend: [{ date: '10-30', count: 42 }],
            registerTrend: [{ date: '10-30', count: 8 }]
        }
    })
    mocks.ranking.mockResolvedValue({
        data: { list: [{ rank: 1, username: '真实排行用户', count: 7 }] }
    })
    mocks.activities.mockResolvedValue({
        data: [
            {
                type: 'operation',
                username: '操作用户',
                description: '更新配置',
                relative_time: '刚刚'
            }
        ]
    })
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [{ path: '/', component: { template: '<div />' } }]
    })
    await router.push('/')
    const wrapper = mount(Workbench, {
        global: {
            plugins: [
                createPinia(),
                router,
                createI18n({ legacy: false, locale: 'zh-CN', messages: { 'zh-CN': zhCN } })
            ],
            stubs: {
                VChart: { props: ['option', 'autoresize'], template: '<div class="chart-stub" />' }
            }
        }
    })
    try {
        await flushPromises()
        expect(wrapper.findAll('.kpi-value').map((card) => card.text())).toEqual([
            '12,345',
            '120',
            '8',
            '42'
        ])
        expect(wrapper.find('.activity-list').text()).toContain('更新配置')
        expect(wrapper.find('.ranking-list').text()).toContain('真实排行用户')
        expect(wrapper.text()).not.toContain('订单占比统计')
        await wrapper.findAll('.trend-card .seg-btn')[1].trigger('click')
        await wrapper.findAll('.ranking-card .seg-btn')[1].trigger('click')
        await flushPromises()
        expect(mocks.stats).toHaveBeenLastCalledWith(30)
        expect(mocks.ranking).toHaveBeenLastCalledWith('week')
    } finally {
        wrapper.unmount()
    }
})
