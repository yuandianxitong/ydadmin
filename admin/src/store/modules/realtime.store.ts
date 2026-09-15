import { defineStore } from 'pinia'
import { ref } from 'vue'

import { realtimeClient, type RealtimeStatus } from '@/utils/realtime'

/**
 * 实时连接状态（M4 spec §5「前端」）。
 * 连接点：router/guards/permission.guard.ts 在 getUserInfo() 成功后调用 connect()；
 * 断开点：utils/auth.ts 的 clearAuthInfo()（登出、401、刷新失败、强制下线都经过它）。
 */
export const useRealtimeStore = defineStore('realtime', () => {
    const status = ref<RealtimeStatus>(realtimeClient.status)
    let bound = false

    function bind() {
        if (bound) {
            return
        }
        bound = true
        realtimeClient.on('status', (next: RealtimeStatus) => {
            status.value = next
        })
    }

    function connect() {
        bind()
        void realtimeClient.connect()
    }

    function disconnect() {
        realtimeClient.disconnect()
    }

    return { status, connect, disconnect }
})

export default useRealtimeStore
