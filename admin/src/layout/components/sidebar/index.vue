<template>
    <aside class="flex h-screen">
        <div v-show="!isMobile" class="contents">
            <standard v-if="settingStore.layoutMode === 'sidebar'" />
            <side v-else />
        </div>
    </aside>
</template>

<script setup lang="ts">
import useAppStore from '@/store/modules/app.store'
import useSettingStore from '@/store/modules/settings.store'

import Side from './side.vue'
import Standard from './standard.vue'

const appStore = useAppStore()
const settingStore = useSettingStore()
const isMobile = computed(() => appStore.isMobile)
</script>

<style lang="scss" scoped></style>
