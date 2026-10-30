<template>
    <div
        class="standard-sidebar h-full flex flex-col"
        :style="{ width: isCollapsed ? '54px' : '220px' }"
    >
        <!-- Logo 区域 -->
        <div
            class="sidebar-brand flex items-center cursor-pointer overflow-hidden flex-shrink-0"
            :class="isCollapsed ? 'justify-center px-2 h-[50px]' : 'px-4 h-[50px]'"
            @click="router.push('/')"
        >
            <img
                :src="appStore.getImageUrl(appStore.config.site_logo)"
                class="w-[32px] h-[32px] rounded-sm flex-shrink-0"
            />
            <span
                v-show="!isCollapsed"
                class="sidebar-site-name ml-2 text-sm font-semibold truncate"
            >
                {{ appStore.config.site_name || '元点Admin' }}
            </span>
        </div>

        <!-- 分割线 -->
        <div class="sidebar-divider mx-2"></div>

        <!-- 菜单区域 -->
        <el-scrollbar class="flex-1">
            <el-menu
                :default-active="activeMenu"
                :collapse="isCollapsed"
                :unique-opened="settingStore.isUniqueOpened"
                mode="vertical"
                background-color="var(--card-bg)"
                text-color="var(--ink-600)"
                active-text-color="var(--ink-600)"
                class="standard-el-menu"
                @select="onMenuSelect"
            >
                <template v-for="item in menuRoutes" :key="item.path">
                    <standard-menu-item :route="item" base-path="" />
                </template>
            </el-menu>
        </el-scrollbar>
    </div>
</template>

<script setup lang="ts">
import { type RouteRecordRaw, useRoute, useRouter } from 'vue-router'

import useAppStore from '@/store/modules/app.store'
import useSettingStore from '@/store/modules/settings.store'
import useUserStore from '@/store/modules/user.store'

import StandardMenuItem from './standard-menu-item.vue'

const route = useRoute()
const router = useRouter()
const appStore = useAppStore()
const settingStore = useSettingStore()
const userStore = useUserStore()

const isCollapsed = computed(() => appStore.isCollapsed)

const menuRoutes = computed(() => {
    return (userStore.routes as RouteRecordRaw[]).filter((r) => !r.meta?.hidden)
})

const activeMenu = computed(() => {
    return (route.meta?.activeMenu as string) || route.path
})

function onMenuSelect(index: string) {
    router.push(index)
}
</script>

<style lang="scss" scoped>
.standard-sidebar {
    background: var(--card-bg);
    border-right: 1px solid var(--ink-100);
    transition: width 0.2s ease;
    overflow: hidden;
}

.sidebar-site-name {
    color: var(--ink-900);
}

.sidebar-brand {
    height: var(--header-h);
}

.sidebar-divider {
    border-top: 1px solid var(--ink-100);
}

.standard-el-menu {
    border-right: none;
    padding: 12px 8px;

    &:not(.el-menu--collapse) {
        width: 220px;
    }

    :deep(> .el-menu-item),
    :deep(> .el-sub-menu) {
        margin: 4px 0;
    }

    :deep(> .el-menu-item),
    :deep(> .el-sub-menu > .el-sub-menu__title) {
        height: 44px;
        line-height: 44px;

        > .standard-menu-label {
            margin-left: 10px;
        }
    }

    &.el-menu--collapse {
        width: 100%;

        :deep(> .el-menu-item),
        :deep(> .el-sub-menu > .el-sub-menu__title),
        :deep(> .el-menu-item > .el-menu-tooltip__trigger) {
            padding: 0;
            justify-content: center;

            > .standard-menu-label {
                display: none;
            }

            > i {
                flex-shrink: 0;
            }
        }
    }

    :deep(.el-menu-item.is-active) {
        color: var(--brand-600);
        background: var(--brand-50);
    }

    :deep(.el-sub-menu__title:hover),
    :deep(.el-menu-item:hover:not(.is-active)) {
        background: var(--ink-50);
    }
}
</style>
