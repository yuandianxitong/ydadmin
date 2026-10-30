<template>
    <header class="topbar-wrap flex flex-col">
        <div class="topbar flex items-center px-5 gap-4">
            <div class="topbar-left flex items-center flex-1 min-w-0">
                <breadcrumb v-if="!isMobile && settingStore.showCrumb" />
            </div>
            <div class="topbar-actions flex items-center gap-1.5">
                <button
                    class="top-btn flex-center w-34px h-34px rounded-lg"
                    :title="$t('header.clearCache')"
                    @click="handleClearCache"
                >
                    <Icon name="i-svg:rotate-ccw" :size="17" />
                </button>
                <button
                    v-if="!isMobile"
                    class="top-btn flex-center w-34px h-34px rounded-lg"
                    :title="isFullscreen ? $t('header.exitFullscreen') : $t('header.fullscreen')"
                    @click="toggleFullscreen"
                >
                    <Icon :name="isFullscreen ? 'i-svg:minimize' : 'i-svg:maximize'" :size="17" />
                </button>
                <notification-bell />
                <lang-select />
                <button
                    class="top-btn flex-center w-34px h-34px rounded-lg"
                    data-appearance-trigger
                    :title="$t('proPage.appearanceTitle')"
                    @click="appearanceRef?.toggle()"
                >
                    <Icon name="i-svg:settings" :size="17" />
                </button>
                <user-drop-down />
            </div>
        </div>
        <multiple-tabs v-if="settingStore.openMultipleTabs" />
    </header>
    <Tweaks ref="appearanceRef" @advanced="openSetting" />
    <layout-setting />
</template>

<script setup lang="ts">
import { useFullscreen } from '@vueuse/core'
import { ElMessage } from 'element-plus'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { clearSystemCache } from '@/api/system/config'
import Tweaks from '@/components/Tweaks/index.vue'
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
const appearanceRef = ref<InstanceType<typeof Tweaks>>()

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
.topbar {
    height: var(--header-h);
    background: var(--card-bg);
    border-bottom: 1px solid var(--ink-100);
}

/* ── Icon action buttons ── */
.top-btn {
    border: none;
    background: transparent;
    color: var(--ink-500);
    cursor: pointer;
    transition:
        background 0.15s,
        color 0.15s;

    &:hover {
        background: var(--ink-100);
        color: var(--ink-800);
    }

    svg {
        width: 17px;
        height: 17px;
    }
}
</style>
