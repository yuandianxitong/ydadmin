/**
 * 管理端 WebSocket 实时客户端（M4 spec §5「前端」、§6「WS」、§7「前端」）。
 *
 * - 连接前先经 HTTP 取一次性票据（30 秒有效），再连 `{WS_BASE}/ws?ticket=…`。
 * - 打开后每 25 秒发 `{event:'ping'}`；服务端 90 秒收不到心跳会以 4000 关闭。
 * - 关闭码：4003（已吊销 / 被踢）不重连并触发一次 force_logout；4001（票据无效）按 nextDelay 退避后重新取票据，
 *   最多 3 次；其余按 nextDelay 指数退避。页面不可见时暂停重连，可见后立即重连。
 * - 依赖可注入（WebSocket 构造、取票据、定时器、document），测试不需要真实网络。
 *
 * import 环纪律：utils/auth.ts → store/modules/realtime.store.ts → 本文件。本文件不得静态 import
 * @/api、@/utils/request、@/utils/auth、@/router（request.ts 静态依赖 auth.ts），默认取票据走动态 import。
 */

export type RealtimeStatus = 'idle' | 'connecting' | 'open' | 'closed'
export type RealtimeEvent =
    | 'connected'
    | 'pong'
    | 'notification.created'
    | 'force_logout'
    | 'task.progress'

// eslint-disable-next-line @typescript-eslint/no-explicit-any -- 各事件 payload 形状不同，由订阅方按事件收窄
export type RealtimeHandler = (payload: any) => void

export const HEARTBEAT_INTERVAL = 25000
export const MAX_RECONNECT_DELAY = 30000
export const MAX_TICKET_RETRIES = 3
export const CLOSE_HEARTBEAT = 4000
export const CLOSE_TICKET = 4001
export const CLOSE_REVOKED = 4003

const SOCKET_OPEN = 1
const PING_FRAME = JSON.stringify({ event: 'ping' })

export interface SocketLike {
    readyState: number
    onopen: ((event: unknown) => void) | null
    onmessage: ((event: { data: unknown }) => void) | null
    onclose: ((event: { code: number }) => void) | null
    onerror: ((event: unknown) => void) | null
    send(data: string): void
    close(code?: number, reason?: string): void
}

export interface VisibilityDocument {
    readonly visibilityState: string
    addEventListener(type: 'visibilitychange', listener: () => void): void
    removeEventListener(type: 'visibilitychange', listener: () => void): void
}

export interface RealtimeDeps {
    createSocket: (url: string) => SocketLike
    fetchTicket: () => Promise<string>
    resolveUrl: (ticket: string) => string
    setTimeout: (callback: () => void, ms: number) => unknown
    clearTimeout: (id: unknown) => void
    setInterval: (callback: () => void, ms: number) => unknown
    clearInterval: (id: unknown) => void
    doc: VisibilityDocument | null
}

/** 第 attempt 次（从 0 起）重连前的等待毫秒数：1s、2s、4s…，上限 30s。 */
export function nextDelay(attempt: number): number {
    return Math.min(1000 * 2 ** Math.max(0, attempt), MAX_RECONNECT_DELAY)
}

/**
 * WS 地址：VITE_APP_WS_URL 优先（如 wss://api.example.com），否则按当前页面推导（https→wss，同 host），
 * 路径固定 /ws。后两个参数只供测试注入。
 */
export function resolveWsUrl(
    ticket: string,
    base: string | undefined = import.meta.env.VITE_APP_WS_URL,
    loc: Pick<Location, 'protocol' | 'host'> = window.location
): string {
    const trimmed = (base ?? '').trim()
    const root =
        trimmed !== ''
            ? trimmed.replace(/\/+$/, '')
            : `${loc.protocol === 'https:' ? 'wss:' : 'ws:'}//${loc.host}`

    return `${root}/ws?ticket=${encodeURIComponent(ticket)}`
}

function defaultDeps(): RealtimeDeps {
    return {
        createSocket: (url) => new WebSocket(url) as unknown as SocketLike,
        fetchTicket: async () => {
            const { realtimeApi } = await import('@/api/realtime')
            const res = await realtimeApi.ticket()
            return res.data.ticket
        },
        resolveUrl: (ticket) => resolveWsUrl(ticket),
        setTimeout: (callback, ms) => globalThis.setTimeout(callback, ms),
        clearTimeout: (id) => globalThis.clearTimeout(id as ReturnType<typeof setTimeout>),
        setInterval: (callback, ms) => globalThis.setInterval(callback, ms),
        clearInterval: (id) => globalThis.clearInterval(id as ReturnType<typeof setInterval>),
        doc: typeof document === 'undefined' ? null : document
    }
}

export class RealtimeClient {
    private readonly deps: RealtimeDeps
    private readonly handlers = new Map<string, Set<RealtimeHandler>>()
    private currentStatus: RealtimeStatus = 'idle'
    private socket: SocketLike | null = null
    private heartbeatId: unknown = null
    private reconnectId: unknown = null
    private attempt = 0
    private ticketRetries = 0
    /** disconnect() 之后为 true：不再重连、忽略迟到的回调。 */
    private manualClose = true
    /** 4003 或票据重试耗尽后为 true：等待下一次 connect()。 */
    private stopped = false
    private pendingReconnect = false
    private forcedOut = false
    private visibilityBound = false
    /** 每次 open() / disconnect() 自增：取票据期间若已被更新的 open() 或 disconnect() 取代，旧的 open() 放弃建连。 */
    private generation = 0

    constructor(deps: Partial<RealtimeDeps> = {}) {
        this.deps = { ...defaultDeps(), ...deps }
    }

    get status(): RealtimeStatus {
        return this.currentStatus
    }

    on(event: RealtimeEvent | 'status', handler: RealtimeHandler): () => void {
        let set = this.handlers.get(event)
        if (!set) {
            set = new Set()
            this.handlers.set(event, set)
        }
        set.add(handler)

        return () => {
            this.handlers.get(event)?.delete(handler)
        }
    }

    /** 幂等：connecting / open 时直接返回。 */
    async connect(): Promise<void> {
        if (this.currentStatus === 'connecting' || this.currentStatus === 'open') {
            return
        }
        this.manualClose = false
        this.stopped = false
        this.forcedOut = false
        this.attempt = 0
        this.ticketRetries = 0
        this.bindVisibility()
        await this.open()
    }

    disconnect(): void {
        this.generation++
        this.manualClose = true
        this.pendingReconnect = false
        this.clearReconnect()
        this.stopHeartbeat()
        this.unbindVisibility()
        const socket = this.socket
        this.socket = null
        socket?.close(1000)
        this.setStatus('idle')
    }

    private async open(): Promise<void> {
        const generation = ++this.generation
        this.clearReconnect()
        this.setStatus('connecting')

        let ticket: string
        try {
            ticket = await this.deps.fetchTicket()
        } catch {
            if (this.manualClose || generation !== this.generation) {
                return
            }
            this.setStatus('closed')
            this.scheduleReconnect()
            return
        }
        if (this.manualClose || generation !== this.generation) {
            return
        }

        const socket = this.deps.createSocket(this.deps.resolveUrl(ticket))
        this.socket = socket
        socket.onopen = () => {
            if (socket !== this.socket) {
                return
            }
            this.attempt = 0
            this.ticketRetries = 0
            this.setStatus('open')
            this.startHeartbeat()
        }
        socket.onmessage = (event) => {
            if (socket === this.socket) {
                this.handleFrame(event.data)
            }
        }
        socket.onclose = (event) => this.handleClose(socket, event.code)
        socket.onerror = () => {
            // 浏览器随后必定触发 onclose，统一在那里处理
        }
    }

    private handleFrame(raw: unknown): void {
        if (typeof raw !== 'string') {
            return
        }
        let frame: unknown
        try {
            frame = JSON.parse(raw)
        } catch {
            return
        }
        if (typeof frame !== 'object' || frame === null) {
            return
        }
        const { event, payload } = frame as { event?: unknown; payload?: unknown }
        if (typeof event !== 'string') {
            return
        }
        if (event === 'force_logout') {
            this.forcedOut = true
        }
        this.emit(event, payload ?? {})
    }

    private handleClose(socket: SocketLike, code: number): void {
        if (socket !== this.socket) {
            return
        }
        this.socket = null
        this.stopHeartbeat()
        this.setStatus('closed')
        if (this.manualClose) {
            return
        }

        if (code === CLOSE_REVOKED) {
            this.stopped = true
            if (!this.forcedOut) {
                this.forcedOut = true
                this.emit('force_logout', { reason: 'revoked', message: '' })
            }
            return
        }
        if (code === CLOSE_TICKET) {
            if (this.ticketRetries >= MAX_TICKET_RETRIES) {
                this.stopped = true
                return
            }
            const delay = nextDelay(this.ticketRetries)
            this.ticketRetries++
            this.runOrDefer(delay)
            return
        }
        this.scheduleReconnect()
    }

    private scheduleReconnect(): void {
        const delay = nextDelay(this.attempt)
        this.attempt++
        this.runOrDefer(delay)
    }

    private runOrDefer(delay: number): void {
        if (this.isHidden()) {
            this.pendingReconnect = true
            return
        }
        this.reconnectId = this.deps.setTimeout(() => {
            this.reconnectId = null
            void this.open()
        }, delay)
    }

    private readonly onVisibilityChange = (): void => {
        if (this.isHidden() || !this.pendingReconnect || this.manualClose || this.stopped) {
            return
        }
        this.pendingReconnect = false
        void this.open()
    }

    private isHidden(): boolean {
        return this.deps.doc !== null && this.deps.doc.visibilityState === 'hidden'
    }

    private bindVisibility(): void {
        if (this.visibilityBound || this.deps.doc === null) {
            return
        }
        this.deps.doc.addEventListener('visibilitychange', this.onVisibilityChange)
        this.visibilityBound = true
    }

    private unbindVisibility(): void {
        if (!this.visibilityBound || this.deps.doc === null) {
            return
        }
        this.deps.doc.removeEventListener('visibilitychange', this.onVisibilityChange)
        this.visibilityBound = false
    }

    private startHeartbeat(): void {
        this.stopHeartbeat()
        this.heartbeatId = this.deps.setInterval(() => {
            if (this.socket !== null && this.socket.readyState === SOCKET_OPEN) {
                this.socket.send(PING_FRAME)
            }
        }, HEARTBEAT_INTERVAL)
    }

    private stopHeartbeat(): void {
        if (this.heartbeatId !== null) {
            this.deps.clearInterval(this.heartbeatId)
            this.heartbeatId = null
        }
    }

    private clearReconnect(): void {
        if (this.reconnectId !== null) {
            this.deps.clearTimeout(this.reconnectId)
            this.reconnectId = null
        }
    }

    private setStatus(status: RealtimeStatus): void {
        if (status === this.currentStatus) {
            return
        }
        this.currentStatus = status
        this.emit('status', status)
    }

    private emit(event: string, payload: unknown): void {
        this.handlers.get(event)?.forEach((handler) => {
            try {
                handler(payload)
            } catch (error) {
                console.error(`[realtime] handler for ${event} failed`, error)
            }
        })
    }
}

export const realtimeClient = new RealtimeClient()
