<template>
    <el-breadcrumb class="app-breadcrumb" separator="/">
        <el-breadcrumb-item v-for="(item, index) in breadcrumbs" :key="item.path">
            <span :class="index === breadcrumbs.length - 1 ? 'crumb-cur' : 'crumb-item'">
                {{ translateRouteTitle(item.meta.title as string, item.name) }}
            </span>
        </el-breadcrumb-item>
    </el-breadcrumb>
</template>
<script setup lang="ts">
import type { RouteLocationMatched, RouteLocationNormalizedLoaded } from 'vue-router'

import { useWatchRoute } from '@/hooks/useWatchRoute'
import { translateRouteTitle } from '@/utils/i18n'

const breadcrumbs = ref<RouteLocationMatched[]>([])
const getBreadcrumb = (route: RouteLocationNormalizedLoaded) => {
    const matched = route.matched.filter((item) => item.meta && item.meta.title)
    breadcrumbs.value = matched
}

useWatchRoute((route) => {
    getBreadcrumb(route)
})
</script>

<style scoped lang="scss">
.app-breadcrumb {
    font-size: 13px;

    :deep(.el-breadcrumb__separator) {
        color: var(--ink-300);
        margin: 0 6px;
    }
}

.crumb-item {
    color: var(--ink-400);
}

.crumb-cur {
    color: var(--ink-700);
    font-weight: 500;
}
</style>
