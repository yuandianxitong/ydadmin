<template>
    <template v-if="!route.meta?.hidden">
        <!-- Leaf node: no visible children -->
        <el-menu-item v-if="!hasVisibleChildren" :index="resolvePath(route.path)">
            <Icon v-if="route.meta?.icon" :name="route.meta.icon" :size="20" />
            <template #title>
                <span class="ml-2">{{
                    translateRouteTitle(route.meta?.title as string, route.name)
                }}</span>
            </template>
        </el-menu-item>

        <!-- Branch node: has visible children -->
        <el-sub-menu v-else :index="resolvePath(route.path)">
            <template #title>
                <Icon v-if="route.meta?.icon" :name="route.meta.icon" :size="20" />
                <span class="ml-2">{{
                    translateRouteTitle(route.meta?.title as string, route.name)
                }}</span>
            </template>
            <standard-menu-item
                v-for="child in visibleChildren"
                :key="resolvePath(child.path)"
                :route="child"
                :base-path="resolvePath(route.path)"
            />
        </el-sub-menu>
    </template>
</template>

<script setup lang="ts">
import type { RouteRecordRaw } from 'vue-router'

import { translateRouteTitle } from '@/utils/i18n'
import { getNormalPath } from '@/utils/url'
import { isExternal } from '@/utils/validate'

interface Props {
    route: RouteRecordRaw
    basePath: string
}

const props = defineProps<Props>()

const visibleChildren = computed(() => {
    return (props.route.children ?? []).filter((item) => !item.meta?.hidden)
})

const hasVisibleChildren = computed(() => {
    return visibleChildren.value.length > 0
})

function resolvePath(path: string): string {
    if (isExternal(path)) return path
    // 绝对路径直接返回（菜单数据的 path 都是绝对路径如 /system/admin）
    if (path.startsWith('/')) return path
    return getNormalPath(`${props.basePath}/${path}`)
}
</script>
