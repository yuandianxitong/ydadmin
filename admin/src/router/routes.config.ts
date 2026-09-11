// src/router/routes.config.ts
import type { RouteRecordRaw } from 'vue-router'

import { PageEnum } from '@/constants/page'

// 推荐直接懒加载 Layout 组件（更符合 Vite 的分包习惯）
export const LAYOUT = () => import('@/layout/index.vue')

// 首页、动态路由挂载的占位名称（父路由 name）
export const INDEX_ROUTE_NAME = 'INDEX_ROUTE'

// 404 独立导出，方便在需要时复用（可选）
export const NOT_FOUND_ROUTE: RouteRecordRaw = {
    path: '/:pathMatch(.*)*',
    component: () => import('@/views/error/404.vue'),
    meta: { hidden: true }
}

// 常量路由：无需权限或基础错误页、登录页等
export const constantRoutes: Array<RouteRecordRaw> = [
    NOT_FOUND_ROUTE,
    {
        path: PageEnum.ERROR_403,
        component: () => import('@/views/error/403.vue'),
        meta: { hidden: true }
    },
    {
        path: PageEnum.ERROR_500,
        component: () => import('@/views/error/500.vue'),
        meta: { hidden: true }
    },
    {
        path: PageEnum.LOGIN,
        component: () => import('@/views/login/index.vue'),
        meta: { hidden: true }
    },
    {
        path: '/diy/editor',
        name: 'DiyEditor',
        component: () => import('@/views/diy/editor-page.vue'),
        meta: { hidden: true, hideTab: true, title: '装修编辑器' }
    }
]

// "动态挂载"父路由：登录后把后端返回的子路由都挂在这里
// redirect 由 permission.guard 动态设置为第一个有效菜单页，此处不能设为 '/'（会自引用死循环）
export const INDEX_ROUTE: RouteRecordRaw = {
    path: PageEnum.INDEX,
    component: LAYOUT,
    name: INDEX_ROUTE_NAME
}
