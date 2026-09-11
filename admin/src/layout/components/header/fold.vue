<template>
    <div class="fold h-full cursor-pointer flex items-center px-2" @click="toggleCollapsed">
        <div :class="`i-svg:${isCollapsed ? 'list-minus' : 'list-plus'}`"></div>
    </div>
</template>

<script setup lang="ts">
import useAppStore from '@/store/modules/app.store'

const appStore = useAppStore()
const isCollapsed = computed(() => appStore.isCollapsed)
// 折叠展开菜单
const toggleCollapsed = () => {
    appStore.toggleCollapsed()
}
</script>
