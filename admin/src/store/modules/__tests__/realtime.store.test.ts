import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, expect, it, vi } from 'vitest'

import { useRealtimeStore } from '../realtime.store'

const mocks = vi.hoisted(() => ({
    handlers: new Map<string, (payload: unknown) => void>(),
    notification: vi.fn(),
    connect: vi.fn()
}))
vi.mock('element-plus', () => ({
    ElNotification: mocks.notification,
    ElMessageBox: { alert: vi.fn() }
}))
vi.mock('@/utils/forceLogout', () => ({ createForceLogoutHandler: () => vi.fn() }))
vi.mock('@/utils/i18n', () => ({ t: (key: string) => key }))
vi.mock('@/utils/realtime', () => ({
    realtimeClient: {
        status: 'idle',
        on: (event: string, callback: (payload: unknown) => void) =>
            mocks.handlers.set(event, callback),
        connect: mocks.connect,
        disconnect: vi.fn()
    }
}))
beforeEach(() => {
    setActivePinia(createPinia())
    mocks.handlers.clear()
    vi.clearAllMocks()
})

it('shows one toast when a notification arrives, without duplicating subscriptions on reconnect', () => {
    const store = useRealtimeStore()
    store.connect()
    store.connect()
    mocks.handlers.get('notification.created')?.({ id: 7, title: 'New notification' })
    expect(mocks.notification).toHaveBeenCalledExactlyOnceWith({
        title: 'notification.title',
        message: 'New notification',
        type: 'info',
        duration: 5000
    })
})
