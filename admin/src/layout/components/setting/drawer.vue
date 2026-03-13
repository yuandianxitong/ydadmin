<template>
    <div class="setting-drawer">
        <el-drawer
            v-model="showSetting"
            append-to-body
            direction="rtl"
            size="320px"
            :title="$t('settings.title')"
            :show-close="true"
        >
            <div class="h-full flex flex-col overflow-y-auto px-1">
                <!-- 主题模式 -->
                <div class="setting-section mb-6">
                    <div class="section-title">{{ $t('settings.themeMode') }}</div>
                    <div class="flex gap-3 mt-3">
                        <div
                            v-for="mode in themeModes"
                            :key="mode.value"
                            class="theme-mode-btn"
                            :class="{ active: settingStore.themeMode === mode.value }"
                            @click="changeThemeMode(mode.value)"
                        >
                            <el-icon :size="20"><component :is="mode.icon" /></el-icon>
                            <span class="text-xs mt-1">{{ mode.label }}</span>
                        </div>
                    </div>
                </div>

                <!-- 主色调 -->
                <div class="setting-section mb-6">
                    <div class="section-title">{{ $t('settings.primaryColor') }}</div>
                    <div class="flex gap-3 mt-3 flex-wrap">
                        <el-tooltip
                            v-for="color in primaryColors"
                            :key="color.value"
                            :content="color.label"
                            placement="top"
                        >
                            <div
                                class="color-swatch"
                                :class="{ active: isActiveColor(color.value) }"
                                :style="{
                                    backgroundColor:
                                        color.value === 'current' ? settingStore.theme : color.value
                                }"
                                @click="changePrimaryColor(color.value)"
                            >
                                <el-icon v-if="isActiveColor(color.value)" color="#fff" :size="14">
                                    <Check />
                                </el-icon>
                            </div>
                        </el-tooltip>
                        <el-color-picker
                            ref="colorPickerRef"
                            v-model="customColor"
                            size="small"
                            class="hidden-color-picker"
                            @change="onCustomColorChange"
                        />
                    </div>
                </div>

                <!-- 暗色主题（仅暗色模式可见） -->
                <div v-if="isDarkMode" class="setting-section mb-6">
                    <div class="section-title">{{ $t('settings.darkTheme') }}</div>
                    <div class="flex gap-3 mt-3 flex-wrap">
                        <el-tooltip
                            v-for="dt in darkThemes"
                            :key="dt.value"
                            :content="dt.label"
                            placement="top"
                        >
                            <div
                                class="dark-theme-swatch"
                                :class="{ active: settingStore.darkTheme === dt.value }"
                                @click="changeDarkTheme(dt.value)"
                            >
                                <div class="dark-theme-preview" :style="{ backgroundColor: dt.bg }">
                                    <div
                                        class="dark-theme-surface"
                                        :style="{ backgroundColor: dt.surface }"
                                    ></div>
                                </div>
                                <span class="text-xs mt-1 text-center">{{ dt.label }}</span>
                            </div>
                        </el-tooltip>
                    </div>
                </div>

                <!-- 布局模式 -->
                <div class="setting-section mb-6">
                    <div class="section-title">{{ $t('settings.layoutMode') }}</div>
                    <div class="flex gap-4 mt-3">
                        <el-tooltip :content="$t('settings.layoutModes.main')" placement="top">
                            <div
                                class="layout-preview"
                                :class="{
                                    active:
                                        settingStore.sideTheme === 'light' ||
                                        settingStore.sideTheme === 'dark'
                                }"
                                @click="changeLayout('main')"
                            >
                                <div class="layout-classic">
                                    <div class="layout-sidebar"></div>
                                    <div class="layout-main">
                                        <div class="layout-header"></div>
                                        <div class="layout-content"></div>
                                    </div>
                                </div>
                            </div>
                        </el-tooltip>
                    </div>
                </div>

                <!-- 其他设置 -->
                <div class="setting-section mb-6">
                    <div class="section-title">{{ $t('settings.otherSettings') }}</div>
                    <div class="mt-3 flex flex-col gap-4">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-tx-secondary">{{
                                $t('settings.tagsView')
                            }}</span>
                            <el-switch v-model="openMultipleTabs" />
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-tx-secondary">{{
                                $t('settings.navigation')
                            }}</span>
                            <el-switch v-model="isUniqueOpened" />
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-tx-secondary">{{
                                $t('settings.sidebarLogo')
                            }}</span>
                            <el-switch v-model="showLogo" />
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-tx-secondary">{{
                                $t('settings.breadcrumb')
                            }}</span>
                            <el-switch v-model="showCrumb" />
                        </div>
                    </div>
                </div>

                <!-- 底部重置 -->
                <div class="mt-auto pt-4 border-t border-solid border-color-border">
                    <el-button class="w-full" @click="resetAll">
                        {{ $t('settings.reset') }}
                    </el-button>
                </div>
            </div>
        </el-drawer>
    </div>
</template>

<script setup lang="ts">
import { Check, Monitor, Moon, Sunny } from '@element-plus/icons-vue'
import { useI18n } from 'vue-i18n'

import useSettingStore from '@/store/modules/settings.store'

const { t } = useI18n()
const settingStore = useSettingStore()
const colorPickerRef = ref()
const customColor = ref(settingStore.theme)

// 预设颜色列表
const presetColors = [
    '#6f42c1', // purple
    '#1f4fff', // blue
    '#18a058', // green
    '#ff6a00', // orange
    '#eb2f96', // magenta
    '#f5222d' // red
]

const themeModes = computed(() => [
    { value: 'system' as const, label: t('settings.themeModes.system'), icon: Monitor },
    { value: 'light' as const, label: t('settings.themeModes.light'), icon: Sunny },
    { value: 'dark' as const, label: t('settings.themeModes.dark'), icon: Moon }
])

const primaryColors = computed(() => [
    { value: 'current', label: t('settings.primaryColors.current') },
    { value: '#6f42c1', label: t('settings.primaryColors.purple') },
    { value: '#1f4fff', label: t('settings.primaryColors.blue') },
    { value: '#18a058', label: t('settings.primaryColors.green') },
    { value: '#ff6a00', label: t('settings.primaryColors.orange') },
    { value: '#eb2f96', label: t('settings.primaryColors.magenta') },
    { value: '#f5222d', label: t('settings.primaryColors.red') }
])

const darkThemes = computed(() => [
    {
        value: 'mint',
        label: t('settings.darkThemes.mint'),
        bg: 'rgb(10,16,20)',
        surface: 'rgb(18,24,28)'
    },
    {
        value: 'navy',
        label: t('settings.darkThemes.navy'),
        bg: 'rgb(14,18,30)',
        surface: 'rgb(22,26,38)'
    },
    {
        value: 'mirage',
        label: t('settings.darkThemes.mirage'),
        bg: 'rgb(5,10,22)',
        surface: 'rgb(13,18,30)'
    },
    {
        value: 'cinder',
        label: t('settings.darkThemes.cinder'),
        bg: 'rgb(14,15,17)',
        surface: 'rgb(22,23,25)'
    },
    {
        value: 'black',
        label: t('settings.darkThemes.black'),
        bg: 'rgb(0,0,0)',
        surface: 'rgb(10,10,10)'
    }
])

const isDarkMode = computed(() => {
    if (settingStore.themeMode === 'dark') return true
    if (settingStore.themeMode === 'light') return false
    return document.documentElement.classList.contains('dark')
})

const showSetting = computed({
    get: () => settingStore.showDrawer,
    set: (v) => settingStore.setSetting({ key: 'showDrawer', value: v })
})

const openMultipleTabs = computed({
    get: () => settingStore.openMultipleTabs,
    set: (v) => settingStore.setSetting({ key: 'openMultipleTabs', value: v })
})

const isUniqueOpened = computed({
    get: () => settingStore.isUniqueOpened,
    set: (v) => settingStore.setSetting({ key: 'isUniqueOpened', value: v })
})

const showLogo = computed({
    get: () => settingStore.showLogo,
    set: (v) => settingStore.setSetting({ key: 'showLogo', value: v })
})

const showCrumb = computed({
    get: () => settingStore.showCrumb,
    set: (v) => settingStore.setSetting({ key: 'showCrumb', value: v })
})

function isActiveColor(value: string): boolean {
    if (value === 'current') {
        return !presetColors.includes(settingStore.theme)
    }
    return settingStore.theme === value
}

function changeThemeMode(mode: 'system' | 'light' | 'dark') {
    settingStore.setSetting({ key: 'themeMode', value: mode })
    settingStore.applyThemeMode()
}

function changePrimaryColor(value: string) {
    if (value === 'current') {
        // Open the color picker
        colorPickerRef.value?.show?.()
        return
    }
    settingStore.setSetting({ key: 'theme', value })
    settingStore.applyThemeMode()
}

function onCustomColorChange(value: string | null) {
    if (value) {
        settingStore.setSetting({ key: 'theme', value })
        settingStore.applyThemeMode()
    }
}

function changeDarkTheme(value: string) {
    settingStore.setSetting({ key: 'darkTheme', value })
    settingStore.applyThemeMode()
}

function changeLayout(_mode: string) {
    // Reserved for future layout modes
}

function resetAll() {
    customColor.value = '#1f4fff'
    settingStore.resetTheme()
}
</script>

<style lang="scss" scoped>
.section-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text-primary);
}

.theme-mode-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 80px;
    height: 60px;
    border-radius: var(--radius-md);
    border: 2px solid var(--color-border);
    cursor: pointer;
    transition: all 0.2s;
    color: var(--color-text-secondary);
    background: var(--color-surface);

    &:hover {
        border-color: var(--color-text-tertiary);
    }

    &.active {
        border-color: var(--el-color-primary);
        color: var(--el-color-primary);
        background: var(--color-brand-ghost);
    }
}

.color-swatch {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    border: 2px solid transparent;

    &:hover {
        transform: scale(1.15);
    }

    &.active {
        box-shadow:
            0 0 0 2px var(--color-bg),
            0 0 0 4px currentColor;
    }
}

.hidden-color-picker {
    width: 0;
    height: 0;
    overflow: hidden;
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.dark-theme-swatch {
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    transition: all 0.2s;

    &.active .dark-theme-preview {
        border-color: var(--el-color-primary);
        box-shadow: 0 0 0 1px var(--el-color-primary);
    }
}

.dark-theme-preview {
    width: 48px;
    height: 36px;
    border-radius: var(--radius-sm);
    border: 2px solid var(--color-border);
    padding: 4px;
    display: flex;
    align-items: flex-end;
    transition: all 0.2s;
}

.dark-theme-surface {
    width: 100%;
    height: 60%;
    border-radius: 2px;
}

.layout-preview {
    width: 80px;
    height: 56px;
    border-radius: var(--radius-sm);
    border: 2px solid var(--color-border);
    cursor: pointer;
    overflow: hidden;
    transition: all 0.2s;

    &:hover {
        border-color: var(--color-text-tertiary);
    }

    &.active {
        border-color: var(--el-color-primary);
    }
}

.layout-classic {
    display: flex;
    width: 100%;
    height: 100%;
}

.layout-sidebar {
    width: 24%;
    height: 100%;
    background: var(--color-text-tertiary);
}

.layout-main {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.layout-header {
    height: 20%;
    background: var(--color-border);
}

.layout-content {
    flex: 1;
    background: var(--gray-100);
}
</style>
