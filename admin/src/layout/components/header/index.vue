<template>
    <header class="header">
        <div class="navbar flex items-center border-b border-b-solid border-b-[var(--color-divider)] px-4 py-2.5">
            <div class="flex-1 flex">
                <div v-if="!isMobile && settingStore.showCrumb" class="flex items-center px-2">
                    <breadcrumb />
                </div>
            </div>
            <div class="flex items-center">
                <div
                    v-if="!isMobile"
                    class="bg-[var(--gray-100)] text-[var(--color-text-secondary)] w-[34px] h-[34px] rounded-full flex items-center justify-center cursor-pointer mr-4"
                    @click="toggleFullscreen"
                >
                    <el-tooltip
                        effect="dark"
                        :content="isFullscreen ? $t('header.exitFullscreen') : $t('header.fullscreen')"
                        placement="bottom"
                    >
                        <Icon name="i-svg:maximize"></Icon>
                    </el-tooltip>
                </div>
                <lang-select />
                <notification-bell />
                <div
                    class="bg-[var(--gray-100)] text-[var(--color-text-secondary)] w-[34px] h-[34px] rounded-full flex items-center justify-center cursor-pointer mr-4"
                    @click="appStore.refreshView()"
                >
                    <el-tooltip class="box-item" effect="dark" :content="$t('header.refresh')" placement="bottom">
                        <Icon name="i-svg:refresh-cw"></Icon>
                    </el-tooltip>
                </div>
                <div
                    class="bg-[var(--gray-100)] text-[var(--color-text-secondary)] w-[34px] h-[34px] rounded-full flex items-center justify-center cursor-pointer mr-4"
                    @click="openSetting"
                >
                    <el-tooltip effect="dark" :content="$t('header.settings')" placement="bottom">
                        <Icon name="i-svg:settings"></Icon>
                    </el-tooltip>
                </div>
                <layout-setting />
                <div class="navbar-item">
                    <user-drop-down />
                </div>
            </div>
        </div>
        <multiple-tabs v-if="settingStore.openMultipleTabs" />
    </header>
</template>

<script setup lang="ts">
import { useFullscreen } from '@vueuse/core'

import useAppStore from '@/store/modules/app.store'
import useSettingStore from '@/store/modules/settings.store'

import LayoutSetting from '../setting/drawer.vue'
import Breadcrumb from './breadcrumb.vue'
import LangSelect from './lang-select.vue'
import Fold from './fold.vue'
import FullScreen from './full-screen.vue'
import MultipleTabs from './multiple-tabs.vue'
import NotificationBell from './notification-bell.vue'
import Refresh from './refresh.vue'
import UserDropDown from './user-drop-down.vue'

const appStore = useAppStore()
const isMobile = computed(() => appStore.isMobile)
const isCollapsed = computed(() => appStore.isCollapsed)
const settingStore = useSettingStore()
const { isFullscreen, toggle: toggleFullscreen } = useFullscreen()

const openSetting = () => {
    settingStore.setSetting({ key: 'showDrawer', value: true })
}
</script>

<style lang="scss">
.navbar {
    height: var(--navbar-height);
}
</style>
