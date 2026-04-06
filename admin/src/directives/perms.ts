import type { App, DirectiveBinding } from 'vue'
import { watch } from 'vue'

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

/**
 * 无权限时直接从 DOM 移除元素（而不是 display:none）
 *
 * 理由：display:none 元素在浏览器 devtools 里仍然可见，
 * 按钮 HTML 对没有权限的用户是暴露的。直接 remove() 更符合安全直觉。
 */
function removeElement(el: HTMLElement) {
    if (el.parentNode) {
        el.parentNode.removeChild(el)
    }
}

// 权限指令实现（OR 关系）
const permission = {
    mounted(el: HTMLElement, binding: DirectiveBinding) {
        const userStore = useUserStore()
        if (!checkPermission(binding.value, userStore)) {
            removeElement(el)
            return
        }

        // 已有权限：监听权限变化，如果后续被撤销则移除元素
        const stop = watch(
            () => userStore.permissions,
            () => {
                if (!checkPermission(binding.value, userStore)) {
                    removeElement(el)
                    stop()
                }
            },
            { deep: true }
        )
        ;(el as any).__permStop = stop
    },

    unmounted(el: HTMLElement) {
        const stop = (el as any).__permStop
        if (typeof stop === 'function') stop()
        delete (el as any).__permStop
    }
}

// 严格权限指令（AND 关系：需要满足所有权限）
const strictPermission = {
    mounted(el: HTMLElement, binding: DirectiveBinding) {
        const userStore = useUserStore()
        const permissions = Array.isArray(binding.value) ? binding.value : [binding.value]

        if (!userStore.hasAllPermissions(permissions)) {
            removeElement(el)
            return
        }

        const stop = watch(
            () => userStore.permissions,
            () => {
                if (!userStore.hasAllPermissions(permissions)) {
                    removeElement(el)
                    stop()
                }
            },
            { deep: true }
        )
        ;(el as any).__permAllStop = stop
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
