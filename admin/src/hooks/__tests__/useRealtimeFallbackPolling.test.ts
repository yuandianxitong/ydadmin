import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { effectScope } from 'vue'

import type { RealtimeClient, RealtimeStatus } from '@/utils/realtime'

import { useRealtimeFallbackPolling } from '../useRealtimeFallbackPolling'

function fakeClient(initial: RealtimeStatus) {
    const handlers = new Set<(status: RealtimeStatus) => void>()
    const client = {
        status: initial,
        on: vi.fn((_event: string, handler: (status: RealtimeStatus) => void) => {
            handlers.add(handler)
            return () => {
                handlers.delete(handler)
            }
        })
    }
    const emit = (status: RealtimeStatus) => {
        client.status = status
        handlers.forEach((handler) => handler(status))
    }
    return {
        client: client as unknown as Pick<RealtimeClient, 'status' | 'on'>,
        emit,
        handlers
    }
}

describe('useRealtimeFallbackPolling', () => {
    beforeEach(() => {
        vi.useFakeTimers()
    })

    afterEach(() => {
        vi.useRealTimers()
    })

    it('fetches once and polls every 60s while the socket is not open', async () => {
        const fetcher = vi.fn()
        const { client } = fakeClient('connecting')
        const scope = effectScope()
        scope.run(() => useRealtimeFallbackPolling(fetcher, { client }))

        expect(fetcher).toHaveBeenCalledTimes(1)
        await vi.advanceTimersByTimeAsync(60000)
        expect(fetcher).toHaveBeenCalledTimes(2)
        await vi.advanceTimersByTimeAsync(60000)
        expect(fetcher).toHaveBeenCalledTimes(3)
        scope.stop()
    })

    it('does not poll when the socket is already open', async () => {
        const fetcher = vi.fn()
        const { client } = fakeClient('open')
        const scope = effectScope()
        scope.run(() => useRealtimeFallbackPolling(fetcher, { client }))

        await vi.advanceTimersByTimeAsync(180000)
        expect(fetcher).toHaveBeenCalledTimes(1)
        scope.stop()
    })

    it('refetches and stops polling on open, resumes polling when it leaves open', async () => {
        const fetcher = vi.fn()
        const { client, emit } = fakeClient('closed')
        const scope = effectScope()
        scope.run(() => useRealtimeFallbackPolling(fetcher, { client }))

        emit('open')
        expect(fetcher).toHaveBeenCalledTimes(2)
        await vi.advanceTimersByTimeAsync(120000)
        expect(fetcher).toHaveBeenCalledTimes(2)

        emit('closed')
        await vi.advanceTimersByTimeAsync(60000)
        expect(fetcher).toHaveBeenCalledTimes(3)
        scope.stop()
    })

    it('stops polling and unsubscribes when the scope is disposed', async () => {
        const fetcher = vi.fn()
        const { client, handlers } = fakeClient('closed')
        const scope = effectScope()
        scope.run(() => useRealtimeFallbackPolling(fetcher, { client }))

        scope.stop()
        await vi.advanceTimersByTimeAsync(120000)

        expect(handlers.size).toBe(0)
        expect(fetcher).toHaveBeenCalledTimes(1)
    })
})
