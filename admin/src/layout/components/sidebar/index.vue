<template>
    <aside class="flex h-screen">
        <div v-show="!isMobile" class="contents"><side /></div>
    </aside>
</template>

<script setup lang="ts">
import useAppStore from '@/store/modules/app.store'

import Side from './side.vue'

const appStore = useAppStore()
const isMobile = computed(() => appStore.isMobile)
</script>

<style lang="scss" scoped></style>
