import { watch } from 'vue'
import { type RouteLocationNormalizedLoaded, useRoute } from 'vue-router'

/**
 * 在路由变化时立即并持续调用回调
 * @param callback 回调函数，接收当前 RouteLocationNormalizedLoaded
 */
export function useWatchRoute(callback: (route: RouteLocationNormalizedLoaded) => void) {
    const route = useRoute()
    watch(
        route,
        () => {
            callback(route)
        },
        { immediate: true }
    )
    return {
        route
    }
}
