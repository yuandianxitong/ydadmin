/**
 * 强制下线处理（M4 spec §7「前端」）：提示 → 清登录信息 → 跳登录页。
 * 依赖全部注入，便于单测；实际接线在 store/modules/realtime.store.ts（全局唯一注册点）。
 */
export interface ForceLogoutPayload {
    reason?: string
    message?: string
}

export interface ForceLogoutDeps {
    alert: (message: string, title: string) => Promise<unknown>
    clearAuth: () => void | Promise<void>
    goLogin: () => void | Promise<void>
    t: (key: string) => string
}

export function createForceLogoutHandler(
    deps: ForceLogoutDeps
): (payload?: ForceLogoutPayload) => Promise<void> {
    let handling = false

    return async (payload) => {
        // 服务端 force_logout 帧与随后的 4003 关闭、或多个标签页的连锁 401 可能接连到达：处理中只认第一条
        if (handling) {
            return
        }
        handling = true
        try {
            const key = payload?.reason === 'kicked' ? 'realtime.kicked' : 'realtime.revoked'
            try {
                await deps.alert(deps.t(key), deps.t('realtime.forceLogoutTitle'))
            } catch {
                // 弹窗被关闭或异常：照样下线
            }
            await deps.clearAuth()
            await deps.goLogin()
        } finally {
            handling = false
        }
    }
}
