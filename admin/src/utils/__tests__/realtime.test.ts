import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import {
    CLOSE_REVOKED,
    CLOSE_TICKET,
    nextDelay,
    RealtimeClient,
    type RealtimeDeps,
    resolveWsUrl,
    type SocketLike,
    type VisibilityDocument
} from '../realtime'

class FakeSocket implements SocketLike {
    static instances: FakeSocket[] = []
    readyState = 0
    onopen: ((event: unknown) => void) | null = null
    onmessage: ((event: { data: unknown }) => void) | null = null
    onclose: ((event: { code: number }) => void) | null = null
    onerror: ((event: unknown) => void) | null = null
    sent: string[] = []
    closedWith: number | undefined = undefined

    constructor(public url: string) {
        FakeSocket.instances.push(this)
    }

    send(data: string): void {
        this.sent.push(data)
    }

    close(code?: number): void {
        this.closedWith = code ?? 1000
        this.readyState = 3
    }

    serverOpen(): void {
        this.readyState = 1
        this.onopen?.({})
    }

    serverMessage(data: unknown): void {
        this.onmessage?.({ data: typeof data === 'string' ? data : JSON.stringify(data) })
    }

    serverClose(code: number): void {
        this.readyState = 3
        this.onclose?.({ code })
    }
}

class FakeDoc implements VisibilityDocument {
    visibilityState = 'visible'
    private listeners = new Set<() => void>()

    addEventListener(_type: 'visibilitychange', listener: () => void): void {
        this.listeners.add(listener)
    }

    removeEventListener(_type: 'visibilitychange', listener: () => void): void {
        this.listeners.delete(listener)
    }

    listenerCount(): number {
        return this.listeners.size
    }

    change(state: 'visible' | 'hidden'): void {
        this.visibilityState = state
        this.listeners.forEach((listener) => listener())
    }
}

function makeClient(overrides: Partial<RealtimeDeps> = {}) {
    const fetchTicket = vi.fn<() => Promise<string>>().mockResolvedValue('t1')
    const doc = new FakeDoc()
    const client = new RealtimeClient({
        createSocket: (url) => new FakeSocket(url),
        fetchTicket,
        resolveUrl: (ticket) => `ws://test/ws?ticket=${ticket}`,
        setTimeout: (callback, ms) => setTimeout(callback, ms),
        clearTimeout: (id) => clearTimeout(id as ReturnType<typeof setTimeout>),
        setInterval: (callback, ms) => setInterval(callback, ms),
        clearInterval: (id) => clearInterval(id as ReturnType<typeof setInterval>),
        doc,
        ...overrides
    })
    return { client, fetchTicket, doc }
}

const flush = () => vi.advanceTimersByTimeAsync(0)
const lastSocket = () => FakeSocket.instances[FakeSocket.instances.length - 1]

describe('nextDelay', () => {
    it('doubles from 1s and caps at 30s', () => {
        expect([0, 1, 2, 3, 4, 5, 6].map(nextDelay)).toEqual([
            1000, 2000, 4000, 8000, 16000, 30000, 30000
        ])
    })
})

describe('resolveWsUrl', () => {
    it('uses VITE_APP_WS_URL as base and strips trailing slashes', () => {
        expect(
            resolveWsUrl('a+b', 'wss://api.example.com/', { protocol: 'http:', host: 'x' })
        ).toBe('wss://api.example.com/ws?ticket=a%2Bb')
    })

    it('derives wss from an https page when base is empty', () => {
        expect(resolveWsUrl('t', '', { protocol: 'https:', host: 'admin.example.com' })).toBe(
            'wss://admin.example.com/ws?ticket=t'
        )
    })

    it('derives ws from an http page when base is undefined', () => {
        expect(resolveWsUrl('t', undefined, { protocol: 'http:', host: '127.0.0.1:5990' })).toBe(
            'ws://127.0.0.1:5990/ws?ticket=t'
        )
    })
})

describe('RealtimeClient', () => {
    beforeEach(() => {
        vi.useFakeTimers()
        FakeSocket.instances = []
    })

    afterEach(() => {
        vi.useRealTimers()
    })

    it('fetches a ticket, opens the socket and reports status changes', async () => {
        const { client, fetchTicket } = makeClient()
        const statuses: string[] = []
        client.on('status', (status) => statuses.push(status))

        await client.connect()
        await flush()

        expect(fetchTicket).toHaveBeenCalledTimes(1)
        expect(lastSocket().url).toBe('ws://test/ws?ticket=t1')
        expect(client.status).toBe('connecting')

        lastSocket().serverOpen()

        expect(client.status).toBe('open')
        expect(statuses).toEqual(['connecting', 'open'])
    })

    it('sends a ping every 25 seconds while open and stops after close', async () => {
        const { client } = makeClient()
        await client.connect()
        await flush()
        const socket = lastSocket()
        socket.serverOpen()

        await vi.advanceTimersByTimeAsync(25000)
        expect(socket.sent).toEqual(['{"event":"ping"}'])
        await vi.advanceTimersByTimeAsync(25000)
        expect(socket.sent).toHaveLength(2)

        socket.serverClose(1006)
        await vi.advanceTimersByTimeAsync(50000)
        expect(socket.sent).toHaveLength(2)
        client.disconnect()
    })

    it('dispatches frames by event, ignores garbage and supports unsubscribe', async () => {
        const { client } = makeClient()
        const received: unknown[] = []
        const off = client.on('notification.created', (payload) => received.push(payload))
        await client.connect()
        await flush()
        const socket = lastSocket()
        socket.serverOpen()

        socket.serverMessage({ event: 'notification.created', payload: { id: 7 }, id: 'x', ts: 1 })
        socket.serverMessage('not json')
        socket.serverMessage({ payload: { id: 8 } })
        off()
        socket.serverMessage({ event: 'notification.created', payload: { id: 9 }, id: 'y', ts: 2 })

        expect(received).toEqual([{ id: 7 }])
        client.disconnect()
    })

    it('reconnects with exponential backoff after an abnormal close', async () => {
        const { client, fetchTicket } = makeClient()
        await client.connect()
        await flush()
        lastSocket().serverClose(1006)

        await vi.advanceTimersByTimeAsync(999)
        expect(FakeSocket.instances).toHaveLength(1)
        await vi.advanceTimersByTimeAsync(1)
        expect(FakeSocket.instances).toHaveLength(2)

        lastSocket().serverClose(1006)
        await vi.advanceTimersByTimeAsync(1999)
        expect(FakeSocket.instances).toHaveLength(2)
        await vi.advanceTimersByTimeAsync(1)
        expect(FakeSocket.instances).toHaveLength(3)
        expect(fetchTicket).toHaveBeenCalledTimes(3)
        client.disconnect()
    })

    it('resets the backoff once a connection opens', async () => {
        const { client } = makeClient()
        await client.connect()
        await flush()
        lastSocket().serverClose(1006)
        await vi.advanceTimersByTimeAsync(1000)
        lastSocket().serverClose(1006)
        await vi.advanceTimersByTimeAsync(2000)
        lastSocket().serverOpen()
        lastSocket().serverClose(1006)

        await vi.advanceTimersByTimeAsync(1000)
        expect(FakeSocket.instances).toHaveLength(4)
        client.disconnect()
    })

    it('treats a ticket fetch failure like an abnormal close', async () => {
        const fetchTicket = vi
            .fn<() => Promise<string>>()
            .mockRejectedValueOnce(new Error('429'))
            .mockResolvedValue('t2')
        const { client } = makeClient({ fetchTicket })

        await client.connect()
        await flush()
        expect(FakeSocket.instances).toHaveLength(0)
        expect(client.status).toBe('closed')

        await vi.advanceTimersByTimeAsync(1000)
        expect(FakeSocket.instances).toHaveLength(1)
        expect(lastSocket().url).toBe('ws://test/ws?ticket=t2')
        client.disconnect()
    })

    it('does not reconnect after 4003 and emits force_logout once with reason revoked', async () => {
        const { client } = makeClient()
        const events: unknown[] = []
        client.on('force_logout', (payload) => events.push(payload))
        await client.connect()
        await flush()
        lastSocket().serverOpen()

        lastSocket().serverClose(CLOSE_REVOKED)
        await vi.advanceTimersByTimeAsync(60000)

        expect(FakeSocket.instances).toHaveLength(1)
        expect(client.status).toBe('closed')
        expect(events).toEqual([{ reason: 'revoked', message: '' }])
    })

    it('does not emit a second force_logout when the server frame precedes 4003', async () => {
        const { client } = makeClient()
        const events: unknown[] = []
        client.on('force_logout', (payload) => events.push(payload))
        await client.connect()
        await flush()
        const socket = lastSocket()
        socket.serverOpen()

        socket.serverMessage({
            event: 'force_logout',
            payload: { reason: 'kicked', message: 'bye' },
            id: 'k',
            ts: 1
        })
        socket.serverClose(CLOSE_REVOKED)
        await vi.advanceTimersByTimeAsync(60000)

        expect(events).toEqual([{ reason: 'kicked', message: 'bye' }])
        expect(FakeSocket.instances).toHaveLength(1)
    })

    it('refetches the ticket immediately on 4001, at most 3 times', async () => {
        const { client, fetchTicket } = makeClient()
        await client.connect()
        await flush()

        for (let i = 0; i < 3; i++) {
            lastSocket().serverClose(CLOSE_TICKET)
            await flush()
        }
        expect(FakeSocket.instances).toHaveLength(4)

        lastSocket().serverClose(CLOSE_TICKET)
        await vi.advanceTimersByTimeAsync(60000)

        expect(FakeSocket.instances).toHaveLength(4)
        expect(fetchTicket).toHaveBeenCalledTimes(4)
        expect(client.status).toBe('closed')
    })

    it('disconnect closes the socket, clears timers and never reconnects', async () => {
        const { client, doc } = makeClient()
        await client.connect()
        await flush()
        const socket = lastSocket()
        socket.serverOpen()

        client.disconnect()
        socket.serverClose(1000)
        await vi.advanceTimersByTimeAsync(60000)

        expect(socket.closedWith).toBe(1000)
        expect(socket.sent).toEqual([])
        expect(client.status).toBe('idle')
        expect(FakeSocket.instances).toHaveLength(1)
        expect(doc.listenerCount()).toBe(0)
    })

    it('pauses reconnect while the page is hidden and reconnects as soon as it becomes visible', async () => {
        const { client, doc } = makeClient()
        await client.connect()
        await flush()
        doc.visibilityState = 'hidden'
        lastSocket().serverClose(1006)

        await vi.advanceTimersByTimeAsync(60000)
        expect(FakeSocket.instances).toHaveLength(1)

        doc.change('visible')
        await flush()
        expect(FakeSocket.instances).toHaveLength(2)
        client.disconnect()
    })
})
