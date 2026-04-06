// src/router/index.ts
import { createRouter, createWebHistory, type RouteRecordRaw, RouterView } from 'vue-router'

import { constantRoutes, INDEX_ROUTE, INDEX_ROUTE_NAME } from '@/router/routes.config'
import useUserStore from '@/store/modules/user.store'
import type { MenuInfo } from '@/types/api'
import { isExternal } from '@/utils/validate'

// 动态导入 views 下的所有 .vue
const modules = import.meta.glob('../views/**/*.vue')

// ========== 工具函数 ==========
export function getModulesKey(): string[] {
    return Object.keys(modules).map((item) =>
        item.replace(/^..\/views\//, '').replace(/\.vue$/, '')
    )
}

/**
 * 动态加载菜单对应的 .vue 组件
 *
 * 找不到时返回 404 组件而不是 RouterView（空白透传），让菜单配置错误可被立刻发现。
 */
export function loadRouteView(component: string) {
    const key = Object.keys(modules).find((key) => key.includes(`/${component}.vue`))
    if (key) return modules[key]

    console.error(
        `[Router] Component not found: ${component}，请确认 src/views/${component}.vue 是否存在。`
    )

    // 降级到 404 页面，避免用户看到纯空白
    const notFoundKey = Object.keys(modules).find((k) => k.endsWith('/error/404.vue'))
    return notFoundKey ? modules[notFoundKey] : RouterView
}

export function filterAsyncRoutes(routes: MenuInfo[], firstRoute = true): RouteRecordRaw[] {
    return routes
        .map((route) => {
            const routeRecord = createRouteRecord(route, firstRoute)
            if (routeRecord && route.children?.length) {
                routeRecord.children = filterAsyncRoutes(route.children, false)
            }
            return routeRecord
        })
        .filter(Boolean) // 过滤掉null值
}

export function createRouteRecord(route: MenuInfo, firstRoute: boolean): RouteRecordRaw {
    // 调试：检查路由数据
    if (!route.path || typeof route.path !== 'string') {
        console.error('Invalid route path:', route)
        return null as any
    }

    const routeRecord = {
        path: isExternal(route.path)
            ? route.path
            : firstRoute
              ? `/${route.path.replace(/^\//, '')}`
              : route.path,
        // 使用稳定字符串 name，避免 Symbol 带来的 hasRoute/removeRoute 不匹配
        name: (() => {
            const raw = (route.name || route.path || '').replace(/^\//, '').replace(/\//g, '_')
            if (!raw) {
                console.warn(`[Router] Route missing name and path, using id fallback:`, route)
                return `route_${route.id || 'unknown'}`
            }
            return raw
        })(),
        meta: {
            hidden: route.meta?.hidden,
            keepAlive: route.meta?.cache,
            title: route.meta?.title,
            perms: route.meta?.permission,
            icon: route.meta?.icon,
            type: route.type || 2,
            activeMenu: route.meta?.activeMenu
        }
    } as unknown as RouteRecordRaw

    // 根据菜单类型设置组件
    // 注意：INDEX_ROUTE 已使用 LAYOUT，动态子路由中的 "LAYOUT" 只需透传 RouterView
    if (route.component === 'LAYOUT') {
        routeRecord.component = RouterView
    } else if (route.component && route.type === 2) {
        // 菜单类型，使用 loadRouteView 函数加载具体组件
        routeRecord.component = loadRouteView(route.component.replace(/^\//, ''))
    } else {
        // 目录类型或未指定组件，使用RouterView
        routeRecord.component = RouterView
    }

    if (route.children?.length) {
        routeRecord.children = filterAsyncRoutes(route.children, false)

        // 目录类型自动设置 redirect 到第一个叶子页面
        if (!route.redirect && route.component === 'LAYOUT') {
            const firstLeaf = findFirstValidPath(routeRecord.children)
            if (firstLeaf) {
                routeRecord.redirect = firstLeaf
            }
        }
    }

    // 使用菜单数据中的 redirect（优先级最高）
    if (route.redirect) {
        routeRecord.redirect = route.redirect
    }

    return routeRecord
}

// 返回第一个有效“可进入页面”的 path（而非 name）
export function findFirstValidPath(routes: RouteRecordRaw[]): string | undefined {
    for (const route of routes) {
        // 检查是否是菜单类型（type = 2）且不隐藏且不是外部链接
        if (route.meta?.type === 2 && !route.meta?.hidden && !isExternal(route.path)) {
            return route.path as string
        }
        if (route.children?.length) {
            const p = findFirstValidPath(route.children)
            if (p) return p
        }
    }
    return undefined
}

export function getRoutePath(perms: string): string {
    const routeList = router.getRoutes()
    const found = routeList.find((item) => item.meta?.perms === perms)
    return found ? found.path : ''
}

export function resetRouter(): void {
    const userStore = useUserStore()

    // 1) 移除父占位（会连带移除其 children）
    if (router.hasRoute(INDEX_ROUTE_NAME as any)) {
        router.removeRoute(INDEX_ROUTE_NAME as any)
    }

    // 2) 保险：遍历用户记录的动态路由逐一移除
    userStore.routes.forEach((route: any) => {
        const name = route.name
        if (name && router.hasRoute(name as string)) {
            router.removeRoute(name as string)
        }
    })

    // 3) 清除 redirect，避免残留的自引用导致死循环
    //    不再提前 addRoute，由 permission.guard 在下次登录时统一挂载
    delete (INDEX_ROUTE as any).redirect

    userStore.isRoutesInited = false
}

// ========== 创建 Router（仅注册常量路由，动态路由由 permission.guard 统一处理） ==========
const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: constantRoutes,
    scrollBehavior: () => ({ left: 0, top: 0 })
})

export default router
