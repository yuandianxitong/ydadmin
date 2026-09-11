<template>
    <div
        class="menu flex-1 min-h-0 mt-4"
        :class="themeClass"
        :style="isCollapsed ? '' : `--aside-width: ${width}px`"
    >
        <el-scrollbar>
            <el-menu
                v-bind="config"
                :default-active="activeMenu"
                :collapse="isCollapsed"
                background-color="var(--color-sidebar-bg)"
                text-color="#fff"
                mode="vertical"
                :unique-opened="uniqueOpened"
                @select="$emit('select')"
            >
                <menu-item
                    v-for="route in routes"
                    :key="route.path"
                    :route="route"
                    :route-path="route.path"
                    :popper-class="themeClass"
                />
            </el-menu>
        </el-scrollbar>
    </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'
import type { RouteRecordRaw } from 'vue-router'

import MenuItem from './menu-item.vue'

const props = defineProps({
    routes: {
        type: Object as PropType<RouteRecordRaw[]>
    },
    config: {
        type: Object
    },
    uniqueOpened: {
        type: Boolean,
        default: false
    },
    isCollapsed: {
        type: Boolean,
        default: false
    },
    theme: {
        type: String
    },
    width: {
        type: Number,
        default: 250
    }
})

defineEmits(['select'])

const route = useRoute()
const activeMenu = computed<string>(() => route.meta?.activeMenu || route.path)
const themeClass = computed(() => `theme-${props.theme}`)
</script>

<style lang="scss" scoped>
.menu {
    .el-menu {
        border-right: none;
        &:not(.el-menu--collapse) {
            width: var(--aside-width);
        }
        :deep(.el-menu-item.is-active) {
            color: #fff;
            background-color: var(--el-menu-active-color);
        }
        :deep(.el-sub-menu__title) {
            opacity: 0.6;
            font-weight: 500;
        }
    }
}
</style>
