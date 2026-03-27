<template>
    <div
        class="standard-sidebar h-full flex flex-col"
        :style="{ width: isCollapsed ? '54px' : '220px' }"
    >
        <!-- Logo 区域 -->
        <div
            class="flex items-center cursor-pointer overflow-hidden flex-shrink-0"
            :class="isCollapsed ? 'justify-center px-2 h-[50px]' : 'px-4 h-[50px]'"
            @click="router.push('/')"
        >
            <img
                :src="appStore.getImageUrl(appStore.config.site_logo)"
                class="w-[32px] h-[32px] rounded-sm flex-shrink-0"
            />
            <span v-show="!isCollapsed" class="ml-2 text-white text-sm font-semibold truncate">
                {{ appStore.config.site_name || '元点Admin' }}
            </span>
        </div>

        <!-- 分割线 -->
        <div class="border-t border-white/8 mx-2"></div>

        <!-- 菜单区域 -->
        <el-scrollbar class="flex-1">
            <el-menu
                :default-active="activeMenu"
                :collapse="isCollapsed"
                :unique-opened="settingStore.isUniqueOpened"
                mode="vertical"
                background-color="var(--color-sidebar-bg)"
                text-color="#fff"
                active-text-color="var(--el-color-primary)"
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
    background-color: var(--color-sidebar-bg);
    transition: width 0.3s ease;
    overflow: hidden;
}

.standard-el-menu {
    border-right: none !important;

    &:not(.el-menu--collapse) {
        width: 220px;
    }

    :deep(.el-menu-item.is-active) {
        background-color: rgba(var(--el-color-primary-rgb, 59, 130, 246), 0.15);
        border-right: 3px solid var(--el-color-primary);
    }

    :deep(.el-sub-menu__title:hover),
    :deep(.el-menu-item:hover:not(.is-active)) {
        background-color: rgba(255, 255, 255, 0.08);
    }
}
</style>
