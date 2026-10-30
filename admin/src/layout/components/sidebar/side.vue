<template>
    <!-- 一级菜单（图标栏） -->
    <aside class="sidebar-main flex-shrink-0 h-full flex flex-col">
        <div class="sidebar-logo flex-center flex-shrink-0">
            <div class="logo-mark flex-center">
                <Logo />
            </div>
        </div>
        <nav class="nav flex flex-col flex-1 px-2.5 py-3 gap-0.5 overflow-y-auto">
            <div
                v-for="item in topRoutes"
                :key="item.path"
                :class="[
                    'nav-item flex flex-col items-center justify-center gap-3 py-4.5 px-1.5 rounded-10px cursor-pointer relative',
                    selectedFirst === item.path ? 'active' : ''
                ]"
                @click="onFirstSelect(item.path)"
            >
                <Icon :name="item.meta?.icon ?? ''" class="nav-ic" />
                <span class="nav-label">{{
                    translateRouteTitle(item.meta?.title, item.name)
                }}</span>
            </div>
        </nav>
    </aside>

    <!-- 二级菜单（子菜单面板） -->
    <aside v-if="secondRoutes.length" class="sidebar-sub flex-shrink-0 h-full flex flex-col">
        <div v-if="selectedFirstRoute" class="sub-head flex items-center px-3.5 flex-shrink-0">
            <span>{{
                translateRouteTitle(selectedFirstRoute.meta?.title, selectedFirstRoute.name)
            }}</span>
        </div>
        <el-scrollbar class="flex-1">
            <div class="sub-list">
                <el-menu
                    :default-active="currentFullPath"
                    router
                    :unique-opened="settingStore.isUniqueOpened"
                    class="h-full !border-none"
                    background-color="transparent"
                    text-color="var(--ink-600)"
                    @select="onSecondSelect"
                >
                    <template v-for="item in secondRoutes">
                        <el-menu-item
                            v-if="!item.children?.length"
                            :key="`item-${resolvePath(item.path)}`"
                            :index="resolvePath(item.path)"
                            class="sub-item"
                        >
                            <span class="sub-dot" />
                            <span class="sub-label">{{
                                translateRouteTitle(item.meta?.title, item.name)
                            }}</span>
                        </el-menu-item>
                        <el-sub-menu
                            v-else
                            :key="`submenu-${resolvePath(item.path)}`"
                            :index="resolvePath(item.path)"
                        >
                            <template #title>
                                <span class="sub-label">{{
                                    translateRouteTitle(item.meta?.title, item.name)
                                }}</span>
                            </template>
                            <el-menu-item
                                v-for="sub in item.children"
                                :key="`item-${resolvePath(sub.path)}`"
                                :index="resolvePath(sub.path)"
                                class="sub-item"
                            >
                                <span class="sub-dot" />
                                <span class="sub-label">{{
                                    translateRouteTitle(sub.meta?.title, sub.name)
                                }}</span>
                            </el-menu-item>
                        </el-sub-menu>
                    </template>
                </el-menu>
            </div>
        </el-scrollbar>
    </aside>
</template>

<script setup lang="ts">
import { type RouteRecordRaw, useRoute, useRouter } from 'vue-router'

import useSettingStore from '@/store/modules/settings.store'
import useUserStore from '@/store/modules/user.store'
import { translateRouteTitle } from '@/utils/i18n'

import Logo from './logo.vue'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()
const settingStore = useSettingStore()
// 使用 computed 保持响应式，当 userStore.setRoutes() 替换数组时自动更新
const routes = computed(() => userStore.routes as RouteRecordRaw[])

// 顶级路由（过滤 hidden）
const topRoutes = computed(() => routes.value.filter((r) => !r.meta?.hidden))

// 根据当前路由匹配，动态计算选中的 “一级” path
const topPaths = computed(() => topRoutes.value.map((r) => r.path))
const selectedFirst = computed<string>(() => {
    // 从 matched 里找第一个在 topPaths 中的
    const match = route.matched.find((m) => topPaths.value.includes(m.path))
    return match ? match.path : topPaths.value[0]
})

// 选中的一级路由对象
const selectedFirstRoute = computed(() => {
    return routes.value.find((r) => r.path === selectedFirst.value)
})

// 根据 selectedFirst 动态计算 “二级” 列表
const secondRoutes = computed(() => {
    const parent = routes.value.find((r) => r.path === selectedFirst.value)
    return (parent?.children || []).filter((r) => !r.meta?.hidden)
})

// 当前完整路由，用于高亮二级菜单
const currentFullPath = computed(() => route.fullPath)

// 递归查找第一个叶子页面路由（type=2）
function findFirstLeafPath(route: RouteRecordRaw, parentPath: string): string | null {
    const fullPath = route.path.startsWith('/') ? route.path : `${parentPath}/${route.path}`
    // 如果是页面类型（type=2）且不隐藏，直接返回
    if (route.meta?.type === 2 && !route.meta?.hidden) {
        return fullPath
    }
    // 如果有子路由，递归查找
    const children = (route.children || []).filter((r) => !r.meta?.hidden)
    for (const child of children) {
        const found = findFirstLeafPath(child, fullPath)
        if (found) return found
    }
    return null
}

// 点击一级菜单时，跳转到第一个叶子页面
function onFirstSelect(path: string) {
    const parent = routes.value.find((r) => r.path === path)
    if (parent) {
        const leafPath = findFirstLeafPath(parent, '')
        if (leafPath) {
            router.push(leafPath)
            return
        }
    }
    router.push(path)
}

// 点击二级菜单
function onSecondSelect(path: string) {
    router.push(path)
}

// 拼接子路由完整 path
function resolvePath(p: string) {
    return p.startsWith('/') ? p : `${selectedFirst.value}/${p}`
}
</script>

<style lang="scss" scoped>
/* ── 第一列：图标栏 ── */
.sidebar-main {
    width: var(--sidebar-w);
    background: #0f172a;
}

.sidebar-logo {
    height: var(--header-h);
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.logo-mark {
    :deep(> div) {
        padding: 0 !important;
        height: auto !important;
    }

    :deep(img) {
        width: 42px;
        height: 42px;
        object-fit: contain;
        display: block;
    }
}

.nav {
    &::-webkit-scrollbar {
        width: 0;
    }
}

.nav-item {
    color: rgba(255, 255, 255, 0.45);
    transition: all 0.18s ease;

    &:hover {
        background: rgba(255, 255, 255, 0.05);
        color: rgba(255, 255, 255, 0.8);
    }

    &.active {
        background: linear-gradient(135deg, var(--brand-500), var(--brand-600));
        box-shadow: 0 6px 18px var(--brand-shadow);
        color: #fff;
    }
}

.nav-ic {
    width: 22px;
    height: 22px;
    display: block;
    flex-shrink: 0;
}

.nav-label {
    font-size: 12px;
    line-height: 1;
    letter-spacing: 0.2px;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
    color: inherit;
}

/* ── 第二列：子菜单面板 ── */
.sidebar-sub {
    width: var(--sidebar-sub-w, 180px);
    background: var(--card-bg);
    border-right: 1px solid var(--ink-100);
    height: 100vh;
}

.sub-head {
    height: var(--header-h);
    font-size: 13px;
    font-weight: 600;
    color: var(--ink-900);
    border-bottom: 1px solid var(--ink-100);
    letter-spacing: 0.5px;
}

.sub-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px;

    &::-webkit-scrollbar {
        width: 4px;
    }

    &::-webkit-scrollbar-thumb {
        background: var(--ink-200);
        border-radius: 2px;
    }
}

/* Override el-menu-item to use sub-item styling */
:deep(.el-menu-item.sub-item) {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px !important;
    font-size: 13px;
    color: var(--ink-600);
    border-radius: 4px;
    height: auto;
    line-height: normal;
    cursor: pointer;
    transition: all 0.15s;

    &:hover {
        background: var(--ink-50) !important;
        color: var(--ink-800) !important;
    }

    &.is-active {
        background: var(--brand-50) !important;
        color: var(--brand-600) !important;
        font-weight: 500;

        .sub-dot {
            background: var(--brand-500);
            box-shadow: 0 0 0 2px var(--brand-100);
        }
    }
}

.sub-dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: var(--ink-300);
    flex-shrink: 0;
}

.sub-label {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* el-sub-menu title styling */
:deep(.el-sub-menu .el-sub-menu__title) {
    padding: 8px 12px !important;
    font-size: 13px;
    color: var(--ink-700);
    font-weight: 500;
    height: auto;
    line-height: normal;
    border-radius: 4px;

    &:hover {
        background: var(--ink-50) !important;
        color: var(--ink-800) !important;
    }
}

:deep(.el-menu--inline) {
    background: transparent !important;
}
</style>
