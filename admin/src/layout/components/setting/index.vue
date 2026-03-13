<template>
    <div class="setting flex cursor-pointer h-full items-center px-2" @click="openSetting">
        <div class="i-svg:setting"></div>
        <layout-setting />
    </div>
</template>

<script setup lang="ts">
import useSettingStore from '@/store/modules/settings.store'

import LayoutSetting from './drawer.vue'

const settingStore = useSettingStore()

const openSetting = () => {
    settingStore.setSetting({
        key: 'showDrawer',
        value: true
    })
}
</script>
