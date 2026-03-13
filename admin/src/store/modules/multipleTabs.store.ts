// src/modules/setting/multipleTabs.store.ts

import { defineStore } from 'pinia'
import { unref } from 'vue'
import type {
    LocationQuery,
    RouteLocationNormalized,
    RouteParamsRaw,
    Router,
    RouteRecordName
} from 'vue-router'

import { PageEnum } from '@/constants/page'
import { isExternal } from '@/utils/validate'

export interface TabItem {
    name: RouteRecordName
    fullPath: string
    path: string
    title?: string
    query?: LocationQuery
    params?: RouteParamsRaw
}

interface MultipleTabsState {
    cacheTabList: Set<string> // 需要 keep-alive 缓存的组件名集合
    tabList: TabItem[] // 当前打开的标签页列表
    tabMap: Record<string, TabItem> // 以 fullPath 为键的快速查找 map
    indexRouteName: RouteRecordName // 首页路由名称，用于“关闭所有”逻辑判断
}

/** 在 tabList 中查找某个 fullPath 的下标 */
const getHasTabIndex = (fullPath: string, tabList: TabItem[]): number => {
    return tabList.findIndex((item) => item.fullPath === fullPath)
}

/** 判断该路由是否不应添加到标签页 */
const isCannotAddRoute = (route: RouteLocationNormalized, router: Router): boolean => {
    const { path, meta, name } = route
    if (!path || isExternal(path)) return true
    if (meta?.hideTab) return true
    if (!router.hasRoute(name!)) return true
    // 登录页和 403 页面不需要出现在标签页
    if (([PageEnum.LOGIN, PageEnum.ERROR_403] as string[]).includes(path)) {
        return true
    }
    return false
}

/** 根据路由信息获取组件名，用于 keep-alive 缓存 */
const getComponentName = (route: RouteLocationNormalized): string | undefined => {
    return route.matched.at(-1)?.components?.default?.name
}

export const useMultipleTabsStore = defineStore('multipleTabs', {
    state: (): MultipleTabsState => ({
        cacheTabList: new Set<string>(),
        tabList: [],
        tabMap: {},
        indexRouteName: '' as RouteRecordName
    }),

    getters: {
        /** 当前所有标签对象数组 */
        getTabList(): TabItem[] {
            return this.tabList
        },
        /** 当前需要被 keep-alive 缓存的组件名数组 */
        getCacheTabList(): string[] {
            return Array.from(this.cacheTabList)
        }
    },

    actions: {
        /** 设置首页路由名称 */
        setIndexRouteName(name: RouteRecordName): void {
            this.indexRouteName = name
        },

        /** 将组件名加入 keep-alive 缓存集合 */
        addCache(componentName?: string): void {
            if (componentName) {
                this.cacheTabList.add(componentName)
            }
        },

        /** 从缓存集合中移除组件名 */
        removeCache(componentName?: string): void {
            if (componentName && this.cacheTabList.has(componentName)) {
                this.cacheTabList.delete(componentName)
            }
        },

        /** 清空所有缓存组件 */
        clearCache(): void {
            this.cacheTabList.clear()
        },

        /** 重置整个 Store */
        resetState(): void {
            this.cacheTabList = new Set<string>()
            this.tabList = []
            this.tabMap = {}
            this.indexRouteName = '' as RouteRecordName
        },

        /**
         * 新增一个标签页
         * @param router 当前 router 实例
         */
        addTab(router: Router): void {
            const route = unref(router.currentRoute)
            const { name, query, meta, params, fullPath, path } = route

            if (isCannotAddRoute(route, router)) {
                return
            }

            const hasTabIndex = getHasTabIndex(fullPath!, this.tabList)
            const componentName = getComponentName(route)
            const tabItem: TabItem = {
                name: name!,
                path,
                fullPath,
                title: meta?.title,
                query,
                params
            }

            // 记录到 tabMap，方便后续查找或移除
            this.tabMap[fullPath] = tabItem

            // 如果需要缓存（keep-alive），则把组件名加到 cacheTabList
            if (meta?.keepAlive) {
                this.addCache(componentName)
            }

            // 已存在则不重复添加
            if (hasTabIndex !== -1) {
                return
            }

            this.tabList.push(tabItem)
        },

        /**
         * 关闭指定 fullPath 对应的标签，并在必要时跳转到相邻标签
         * @param fullPath 要关闭的标签 fullPath
         * @param router 当前 router 实例
         */
        removeTab(fullPath: string, router: Router): void {
            const { currentRoute, push } = router
            const index = getHasTabIndex(fullPath, this.tabList)

            // 只有当 tabList 长度 > 1 且找到该索引时，才执行删除
            if (this.tabList.length > 1 && index !== -1) {
                this.tabList.splice(index, 1)
            }

            // 从 cache 中移除当前活动组件
            const currentComponentName = getComponentName(currentRoute.value)
            this.removeCache(currentComponentName)

            // 如果要删除的并非当前激活 tab，则无需跳转
            if (fullPath !== currentRoute.value.fullPath) {
                return
            }

            // 要删除的是当前激活标签，需要跳转到相邻 tab
            let toTab: TabItem | null = null
            if (index === 0) {
                // 删除第一个标签，跳转到剩余的第一个
                toTab = this.tabList[index]
            } else {
                // 否则跳转到前一个标签
                toTab = this.tabList[index - 1]
            }

            // 直接调用 router.push，用 tabItem 的 path、query、params
            push({
                path: toTab.path,
                query: toTab.query,
                params: toTab.params
            })
        },

        /**
         * 关闭其他标签，只保留传入路由对应的标签
         * @param route 要保留的路由对象
         */
        removeOtherTab(route: RouteLocationNormalized): void {
            this.tabList = this.tabList.filter((item) => item.fullPath === route.fullPath)
            const componentName = getComponentName(route)
            // 清空其他组件的缓存
            this.cacheTabList.forEach((name) => {
                if (componentName !== name) {
                    this.removeCache(name)
                }
            })
        },

        /**
         * 关闭所有标签。
         * 如果当前处于首页，则仅保留该首页标签；否则跳转到首页。
         * @param router 当前 router 实例
         */
        removeAllTab(router: Router): void {
            const { push, currentRoute } = router
            const { name } = unref(currentRoute)

            // 如果已经在首页，则仅保留当前标签
            if (name === this.indexRouteName) {
                this.removeOtherTab(currentRoute.value)
                return
            }

            // 否则清空所有标签和缓存，并跳到首页
            this.tabList = []
            this.clearCache()
            push(PageEnum.INDEX)
        }
    }
})

export default useMultipleTabsStore
