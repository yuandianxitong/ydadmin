<script setup lang="ts">
import { useThrottleFn, useWindowSize } from '@vueuse/core'
import en from 'element-plus/es/locale/lang/en'
import zhCn from 'element-plus/es/locale/lang/zh-cn'
import { computed } from 'vue'

import ErrorBoundary from './components/ErrorBoundary.vue'
import { ScreenEnum } from './constants/app'
import { getLocale } from './locales/setupI18n'
import useAppStore from './store/modules/app.store'
import useSettingStore from './store/modules/settings.store'

const appStore = useAppStore()
const settingStore = useSettingStore()

const elLocale = computed(() => {
    return getLocale() === 'en-US' ? en : zhCn
})

onMounted(() => {
    settingStore.applyThemeMode()
})

const { width } = useWindowSize()
watch(
    width,
    useThrottleFn((value) => {
        if (value > ScreenEnum.SM) {
            appStore.setMobile(false)
            appStore.toggleCollapsed(false)
        } else {
            appStore.setMobile(true)
            appStore.toggleCollapsed(true)
        }
        if (value < ScreenEnum.MD) {
            appStore.toggleCollapsed(true)
        }
    }),
    {
        immediate: true
    }
)
</script>

<template>
    <el-config-provider :locale="elLocale" :z-index="3000">
        <ErrorBoundary>
            <router-view />
        </ErrorBoundary>
    </el-config-provider>
</template>

<style></style>
