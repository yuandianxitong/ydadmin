import { flushPromises, mount } from '@vue/test-utils'
import { expect, it, vi } from 'vitest'
import { createI18n } from 'vue-i18n'

import NotificationBell from './notification-bell.vue'

const mocks = vi.hoisted(() => ({
    handlers: new Map<string, () => void>(),
    count: vi.fn()
}))
vi.mock('@/api/notification', () => ({ notificationApi: { getUnreadCount: mocks.count } }))
vi.mock('@/utils/realtime', () => ({
    realtimeClient: { on: (event: string, handler: () => void) => {
        mocks.handlers.set(event, handler)
        return () => mocks.handlers.delete(event)
    } }
}))
vi.mock('@/hooks/useRealtimeFallbackPolling', () => ({
    useRealtimeFallbackPolling: (refresh: () => void) => refresh()
}))

it('refreshes the badge from the server when a notification is removed', async () => {
    mocks.count.mockResolvedValueOnce({ data: { count: 1 } }).mockResolvedValueOnce({ data: { count: 0 } })
    const wrapper = mount(NotificationBell, {
        global: {
            plugins: [createI18n({ legacy: false, locale: 'zh-CN', missingWarn: false, fallbackWarn: false })],
            stubs: { Icon: true }
        }
    })
    try {
        await flushPromises()
        expect(wrapper.findComponent({ name: 'ElBadge' }).props('hidden')).toBe(false)
        mocks.handlers.get('notification.changed')?.()
        await flushPromises()
        expect(wrapper.findComponent({ name: 'ElBadge' }).props('hidden')).toBe(true)
        expect(mocks.count).toHaveBeenCalledTimes(2)
    } finally {
        wrapper.unmount()
    }
    expect(mocks.handlers.has('notification.changed')).toBe(false)
})
