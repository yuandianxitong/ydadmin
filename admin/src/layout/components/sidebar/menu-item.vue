<template>
    <template v-if="!route.meta?.hidden">
        <app-link v-if="!hasShowChild" :to="`${routePath}?${queryStr}`">
            <el-menu-item :index="routePath">
                <Icon :name="`${routeMeta?.icon}`" :size="24" />
                <template #title>
                    <span class="ml-2">{{
                        translateRouteTitle(routeMeta?.title as string, route.name)
                    }}</span>
                </template>
            </el-menu-item>
        </app-link>
        <el-sub-menu v-else :index="routePath" :popper-class="popperClass">
            <template #title>
                <Icon v-if="routeMeta?.icon" :size="24" :name="routeMeta?.icon" />
                <span class="ml-2">{{
                    translateRouteTitle(routeMeta?.title as string, route.name)
                }}</span>
            </template>
            <menu-item
                v-for="item in route?.children"
                :key="resolvePath(item.path)"
                :route="item"
                :route-path="resolvePath(item.path)"
                :popper-class="popperClass"
            />
        </el-sub-menu>
    </template>
</template>

<script lang="ts" setup>
import type { RouteRecordRaw } from 'vue-router'

import { translateRouteTitle } from '@/utils/i18n'
import { getNormalPath, objectToQuery } from '@/utils/util'
import { isExternal } from '@/utils/validate'

interface Props {
    route: RouteRecordRaw
    routePath: string
    popperClass: string
}

const props = defineProps<Props>()

const hasShowChild = computed(() => {
    const children: RouteRecordRaw[] = props.route.children ?? []
    return !!children.filter((item) => !item.meta?.hidden).length
})

const routeMeta = computed(() => {
    return props.route.meta
})

const resolvePath = (path: string) => {
    if (isExternal(path)) {
        return path
    }
    const newPath = getNormalPath(`${props.routePath}/${path}`)
    return newPath
}
const queryStr = computed<string>(() => {
    const query = props.route.meta?.query as string
    try {
        const queryObj = JSON.parse(query)
        return objectToQuery(queryObj)
    } catch (error) {
        // console.log(error)

        return query
    }
})

const isElIcon = computed(() => routeMeta.value?.icon?.startsWith('el-icon'))
const iconComponent = computed(() => routeMeta.value?.icon?.replace('el-icon-', ''))
</script>
<style lang="scss" scoped>
.el-icon {
    width: 14px !important;
    margin-right: 0 !important;
    color: currentcolor;
}

[class^='i-svg:'] {
    width: 14px;
    height: 14px;
    color: currentcolor !important;
}

.el-menu-item,
.el-sub-menu__title {
    .menu-item-icon {
        margin-right: 8px;
        width: var(--el-menu-icon-width);
        text-align: center;
        vertical-align: middle;
    }
}
</style>
