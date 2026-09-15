import { getCurrentScope, onScopeDispose } from 'vue'

import { type RealtimeClient, realtimeClient, type RealtimeStatus } from '@/utils/realtime'

/**
 * WS 在线时靠推送、断线时回退轮询（M4 spec §7「前端」）。
 *
 * - 调用时立即执行一次 fetcher。
 * - 状态非 open：每 interval 毫秒执行一次 fetcher。
 * - 进入 open：立即补拉一次（弥补断线期间错过的推送）并停止轮询；离开 open 恢复轮询。
 * - 在组件 setup / effectScope 内调用时随作用域自动 dispose；也可手动调用返回的函数。
 */
export function useRealtimeFallbackPolling(
    fetcher: () => unknown,
    options: { interval?: number; client?: Pick<RealtimeClient, 'status' | 'on'> } = {}
): () => void {
    const interval = options.interval ?? 60000
    const client = options.client ?? realtimeClient
    let timer: ReturnType<typeof setInterval> | null = null

    const startPolling = () => {
        if (timer === null) {
            timer = setInterval(() => {
                void fetcher()
            }, interval)
        }
    }

    const stopPolling = () => {
        if (timer !== null) {
            clearInterval(timer)
            timer = null
        }
    }

    void fetcher()
    if (client.status !== 'open') {
        startPolling()
    }

    const off = client.on('status', (status: RealtimeStatus) => {
        if (status === 'open') {
            stopPolling()
            void fetcher()
        } else {
            startPolling()
        }
    })

    const dispose = () => {
        off()
        stopPolling()
    }

    if (getCurrentScope()) {
        onScopeDispose(dispose)
    }

    return dispose
}
