import type { App, DirectiveBinding } from 'vue'
import { nextTick, watch } from 'vue'

import { useUserStore } from '@/store'

// 检查权限的核心函数
function checkPermission(permissions: string | string[], userStore: any): boolean {
    if (!permissions) return true

    if (Array.isArray(permissions)) {
        // 数组权限：满足其中任意一个即可（OR关系）
        return userStore.hasAnyPermission(permissions)
    } else {
        // 单个权限
        return userStore.hasPermission(permissions)
    }
}

// 权限指令实现
const permission = {
    mounted(el: HTMLElement, binding: DirectiveBinding) {
        const userStore = useUserStore()
        const apply = () => {
            const ok = checkPermission(binding.value, userStore)
            if (!ok) {
                el.style.display = 'none'
                el.setAttribute('data-permission-hidden', 'true')
            } else {
                el.style.display = ''
                el.removeAttribute('data-permission-hidden')
            }
        }

        // 首次应用
        apply()

        // 监听权限变化，动态更新
        const stop = watch(
            () => userStore.permissions,
            () => {
                apply()
            },
            { deep: true }
        )
        ;(el as any).__permStop = stop
    },

    updated(el: HTMLElement, binding: DirectiveBinding) {
        const userStore = useUserStore()
        const ok = checkPermission(binding.value, userStore)
        if (!ok) {
            el.style.display = 'none'
            el.setAttribute('data-permission-hidden', 'true')
        } else {
            el.style.display = ''
            el.removeAttribute('data-permission-hidden')
        }
    },

    unmounted(el: HTMLElement) {
        const stop = (el as any).__permStop
        if (typeof stop === 'function') stop()
        delete (el as any).__permStop
    }
}

// 严格权限指令（需要满足所有权限，AND关系）
const strictPermission = {
    mounted(el: HTMLElement, binding: DirectiveBinding) {
        const userStore = useUserStore()
        const permissions = Array.isArray(binding.value) ? binding.value : [binding.value]
        const apply = () => {
            const ok = userStore.hasAllPermissions(permissions)
            if (!ok) {
                el.style.display = 'none'
                el.setAttribute('data-permission-hidden', 'true')
            } else {
                el.style.display = ''
                el.removeAttribute('data-permission-hidden')
            }
        }
        apply()
        const stop = watch(
            () => userStore.permissions,
            () => apply(),
            { deep: true }
        )
        ;(el as any).__permAllStop = stop
    },

    updated(el: HTMLElement, binding: DirectiveBinding) {
        const userStore = useUserStore()
        const permissions = Array.isArray(binding.value) ? binding.value : [binding.value]
        const ok = userStore.hasAllPermissions(permissions)
        if (!ok) {
            el.style.display = 'none'
            el.setAttribute('data-permission-hidden', 'true')
        } else {
            el.style.display = ''
            el.removeAttribute('data-permission-hidden')
        }
    },

    unmounted(el: HTMLElement) {
        const stop = (el as any).__permAllStop
        if (typeof stop === 'function') stop()
        delete (el as any).__permAllStop
    }
}

// 安装权限指令
export default {
    install(app: App) {
        // v-has-perm 指令（OR关系，满足任意一个权限即可）
        app.directive('hasPerm', permission)

        // v-has-all-perm 指令（AND关系，需要满足所有权限）
        app.directive('hasAllPerm', strictPermission)

        // 保持向后兼容
        app.directive('permission', permission)
        app.directive('perms', permission)
    }
}
