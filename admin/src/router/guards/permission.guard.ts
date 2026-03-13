// src/router/guards/permission.guard.ts

import 'nprogress/nprogress.css'

import NProgress from 'nprogress'
import type { Router, RouteRecordRaw } from 'vue-router'

import { appConfig } from '@/constants' // 全局配置（比如站点标题）
import { translateRouteTitle } from '@/utils/i18n'
import { PageEnum } from '@/constants/page' // 路由常量
import router from '@/router' // 路由实例（src/router/index.ts）
import { filterAsyncRoutes, findFirstValidPath } from '@/router/index' // 从 router/index 导出
import { INDEX_ROUTE, INDEX_ROUTE_NAME } from '@/router/routes.config' // 从 routes.config 导出
import useTabsStore from '@/store/modules/multipleTabs.store'
import useUserStore from '@/store/modules/user.store' // Pinia: 用户信息 store
import { clearAuthInfo } from '@/utils/auth' // 清除本地登录态（比如清空 Token)
import { isExternal } from '@/utils/validate' // 工具：判断 URL 是否外部链接

/**
 * 创建并注册“登录鉴权与动态路由”守卫
 */
export default function createPermissionGuard(router: Router): void {
    // 白名单：无需登录即可访问的 path
    const whiteList: string[] = [PageEnum.LOGIN, PageEnum.ERROR_403]

    router.beforeEach(async (to, from, next) => {
        // 顶部进度条开始
        NProgress.start()

        // 设置页面标题，如果 meta.title 不是字符串，就使用默认 appConfig.title
        const metaTitle = to.meta.title
        const translatedTitle = translateRouteTitle(typeof metaTitle === 'string' ? metaTitle : '', to.name)
        document.title = translatedTitle || appConfig.title

        const userStore = useUserStore()
        const tabsStore = useTabsStore()

        // 白名单放行
        if (whiteList.includes(to.path)) {
            next()
            return
        }

        const token = userStore.token
        if (token) {
            // 同时检查用户信息和动态路由是否已初始化
            // login() 会设置 userInfo 但不会加载路由，因此必须同时检查 isRoutesInited
            const hasUserInfo = Object.keys(userStore.userInfo).length > 0
            if (hasUserInfo && userStore.isRoutesInited) {
                if (to.path === PageEnum.LOGIN) {
                    next({ path: PageEnum.INDEX })
                } else {
                    next()
                }
            } else {
                try {
                    // 拉取用户信息与菜单（原始菜单结构）
                    await userStore.getUserInfo()
                    const rawRoutes = await userStore.fetchMenus()

                    // 先根据 rawRoutes 生成 RouteRecordRaw[]（包含 component 动态加载）
                    const accessedRoutes: RouteRecordRaw[] = filterAsyncRoutes(rawRoutes)

                    // 找到第一个有效页面的 path
                    const firstRoutePath = findFirstValidPath(accessedRoutes)
                    if (!firstRoutePath) {
                        clearAuthInfo()
                        next(PageEnum.ERROR_403)
                        return
                    }

                    // 动态修改 INDEX_ROUTE 的 redirect（按 path 重定向更稳妥）
                    INDEX_ROUTE.redirect = { path: firstRoutePath as string }

                    // 把 INDEX_ROUTE(‘/’) 挂到路由实例下
                    router.addRoute(INDEX_ROUTE)

                    // 将所有的 accessedRoutes 作为 INDEX_ROUTE 的子路由一次性挂载
                    accessedRoutes.forEach((route) => {
                        router.addRoute(INDEX_ROUTE_NAME as any, route)
                    })

                    // 回写已挂载的动态路由，避免重复挂载
                    userStore.setRoutes(accessedRoutes)

                    // 设置多标签页中的首页“名称”（从路由表中按 path 反查 name）
                    const first = router.getRoutes().find((r) => r.path === firstRoutePath)
                    if (first && first.name) {
                        tabsStore.setIndexRouteName(first.name)
                    }

                    // 导航到原目标，并 replace，避免多余的历史记录
                    next({ ...to, replace: true })
                } catch (error) {
                    clearAuthInfo()
                    next({ path: PageEnum.LOGIN, query: { redirect: to.fullPath } })
                }
            }
        } else {
            // 无 token，跳转登录页并携带重定向参数
            next({ path: PageEnum.LOGIN, query: { redirect: to.fullPath } })
        }
    })

    router.afterEach(() => {
        NProgress.done()
    })
}
