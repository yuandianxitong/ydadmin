// src/store/modules/realtime.store.ts
import { ElMessageBox, ElNotification } from 'element-plus'
import { defineStore } from 'pinia'
import { ref } from 'vue'

import { PageEnum } from '@/constants/page'
import { createForceLogoutHandler, type ForceLogoutPayload } from '@/utils/forceLogout'
import { t } from '@/utils/i18n'
import { realtimeClient, type RealtimeStatus } from '@/utils/realtime'

/**
 * 全局唯一的强制下线处理。auth.ts 静态 import 本 store、router 的守卫也静态 import 本 store，
 * 所以 clearAuthInfo 与 router 只能动态 import，否则形成静态环。
 */
const handleForceLogout = createForceLogoutHandler({
    alert: (message, title) =>
        ElMessageBox.alert(message, title, {
            type: 'warning',
            showClose: false,
            closeOnPressEscape: false
        }),
    clearAuth: async () => {
        const { clearAuthInfo } = await import('@/utils/auth')
        clearAuthInfo()
    },
    goLogin: async () => {
        const { default: router } = await import('@/router')
        if (router.currentRoute.value.path !== PageEnum.LOGIN) {
            await router.push(PageEnum.LOGIN)
        }
    },
    t
})

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
        realtimeClient.on('force_logout', (payload: ForceLogoutPayload) => {
            void handleForceLogout(payload)
        })
        const progressToasts = new Map<string, { close: () => void }>()
        realtimeClient.on(
            'task.progress',
            (payload: { task_id?: string; percent?: number; message?: string }) => {
                const taskId = String(payload.task_id ?? '')
                if (taskId === '') {
                    return
                }
                const percent = Math.max(0, Math.min(100, Number(payload.percent ?? 0)))
                const message = String(payload.message ?? '')
                progressToasts.get(taskId)?.close()
                const toast = ElNotification({
                    title: t('taskProgress.title'),
                    message: `${percent}% ${message}`,
                    duration: percent >= 100 ? 3000 : 0,
                    type: percent >= 100 ? 'success' : 'info'
                })
                if (percent >= 100) {
                    // 任务完成后这条就不会再更新了，留在 Map 里只会一直涨
                    progressToasts.delete(taskId)
                } else {
                    progressToasts.set(taskId, toast)
                }
            }
        )
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
