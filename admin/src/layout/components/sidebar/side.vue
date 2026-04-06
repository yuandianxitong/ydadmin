<template>
    <!-- 一级菜单 -->
    <aside class="flex-shrink-0 w-[60px] h-full bg-sidebar flex flex-col items-center pt-2">
        <Logo />
        <ul class="w-full space-y-4 text-white px-2 pt-4">
            <li
                v-for="item in topRoutes"
                :key="item.path"
                :class="[
                    'menu-item relative w-full h-[50px] flex justify-center items-center cursor-pointer rounded-lg transition-all duration-500 ease-in-out',
                    selectedFirst === item.path ? 'is-active bg-white/15' : 'hover:bg-white/10'
                ]"
                @click="onFirstSelect(item.path)"
            >
                <el-tooltip
                    :content="translateRouteTitle(item.meta?.title, item.name)"
                    placement="right"
                >
                    <Icon
                        :name="item.meta?.icon ?? ''"
                        :size="item.meta?.icon?.startsWith('i-svg:') ? 20 : 22"
                        class="text-white"
                    />
                </el-tooltip>
            </li>
        </ul>
    </aside>

    <!-- 二级菜单 -->
    <aside
        v-if="secondRoutes.length"
        class="flex-shrink-0 w-[180px] h-full bg-surface shadow-[1px_1px_4px_rgba(0,21,41,0.08)] flex flex-col"
    >
        <div v-if="selectedFirstRoute" class="second-menu-header">
            <Icon
                v-if="selectedFirstRoute.meta?.icon"
                :name="selectedFirstRoute.meta.icon"
                size="18"
                class="text-[var(--el-color-primary)]"
            />
            <span>{{
                translateRouteTitle(selectedFirstRoute.meta?.title, selectedFirstRoute.name)
            }}</span>
        </div>
        <el-scrollbar class="flex-1">
            <el-menu
                :default-active="currentFullPath"
                router
                unique-opened
                class="h-full !border-none"
                background-color="transparent"
                text-color="var(--color-text-primary)"
                @select="onSecondSelect"
            >
                <template v-for="item in secondRoutes">
                    <el-menu-item
                        v-if="!item.children?.length"
                        :key="`item-${resolvePath(item.path)}`"
                        :index="resolvePath(item.path)"
                        class="flex items-center"
                    >
                        <Icon v-if="item.meta?.icon" :name="item.meta.icon" size="20" />
                        <span class="ml-2">{{
                            translateRouteTitle(item.meta?.title, item.name)
                        }}</span>
                    </el-menu-item>
                    <el-sub-menu
                        v-else
                        :key="`submenu-${resolvePath(item.path)}`"
                        :index="resolvePath(item.path)"
                    >
                        <template #title>
                            <Icon v-if="item.meta?.icon" :name="item.meta.icon" size="20" />
                            <span class="ml-2">{{
                                translateRouteTitle(item.meta?.title, item.name)
                            }}</span>
                        </template>
                        <el-menu-item
                            v-for="sub in item.children"
                            :key="`item-${resolvePath(sub.path)}`"
                            :index="resolvePath(sub.path)"
                        >
                            <span>{{ translateRouteTitle(sub.meta?.title, sub.name) }}</span>
                        </el-menu-item>
                    </el-sub-menu>
                </template>
            </el-menu>
        </el-scrollbar>
    </aside>
</template>

<script setup lang="ts">
import { type RouteRecordRaw, useRoute, useRouter } from 'vue-router'

import useUserStore from '@/store/modules/user.store'
import { translateRouteTitle } from '@/utils/i18n'

import Logo from './logo.vue'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()
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
.second-menu-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 16px 20px;
    font-weight: 600;
    font-size: 15px;
    color: var(--el-text-color-primary);
    border-bottom: 1px solid var(--el-border-color-light, #f0f0f0);
    flex-shrink: 0;
}

.menu-item.is-active::before {
    content: '';
    position: absolute;
    left: 44px;
    top: 16px;
    border-right: 8px solid var(--color-surface, #f8f9fb);
    border-top: 8px solid transparent;
    border-bottom: 8px solid transparent;
}
</style>
