<template>
    <header class="header">
        <div
            class="navbar flex items-center border-b border-b-solid border-b-[var(--color-divider)] px-4 py-2.5"
        >
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
                        :content="
                            isFullscreen ? $t('header.exitFullscreen') : $t('header.fullscreen')
                        "
                        placement="bottom"
                    >
                        <Icon name="i-svg:maximize"></Icon>
                    </el-tooltip>
                </div>
                <lang-select />
                <notification-bell />
                <div
                    class="bg-[var(--gray-100)] text-[var(--color-text-secondary)] w-[34px] h-[34px] rounded-full flex items-center justify-center cursor-pointer mr-4"
                    @click="handleClearCache"
                >
                    <el-tooltip
                        class="box-item"
                        effect="dark"
                        :content="$t('header.clearCache')"
                        placement="bottom"
                    >
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
import { ElMessage } from 'element-plus'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { clearSystemCache } from '@/api/system/config'
import useAppStore from '@/store/modules/app.store'
import useSettingStore from '@/store/modules/settings.store'

import LayoutSetting from '../setting/drawer.vue'
import Breadcrumb from './breadcrumb.vue'
import LangSelect from './lang-select.vue'
import MultipleTabs from './multiple-tabs.vue'
import NotificationBell from './notification-bell.vue'
import UserDropDown from './user-drop-down.vue'

const { t } = useI18n()
const appStore = useAppStore()
const isMobile = computed(() => appStore.isMobile)
const isCollapsed = computed(() => appStore.isCollapsed)
const settingStore = useSettingStore()
const { isFullscreen, toggle: toggleFullscreen } = useFullscreen()
const cacheClearing = ref(false)

const openSetting = () => {
    settingStore.setSetting({ key: 'showDrawer', value: true })
}

const handleClearCache = async () => {
    if (cacheClearing.value) return
    cacheClearing.value = true
    try {
        await clearSystemCache()
        ElMessage.success(t('header.clearCacheSuccess'))
        appStore.refreshView()
    } catch {
        ElMessage.error(t('header.clearCacheFailed'))
    } finally {
        cacheClearing.value = false
    }
}
</script>

<style lang="scss">
.navbar {
    height: var(--navbar-height);
}
</style>
