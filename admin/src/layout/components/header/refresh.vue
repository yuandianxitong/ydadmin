<template>
    <div class="refresh cursor-pointer h-full flex items-center px-2" @click="refreshView">
        <div class="i-svg:rotate-ccw"></div>
    </div>
</template>

<script setup lang="ts">
import useAppStore from '@/store/modules/app.store'

const appStore = useAppStore()
// 刷新页面
const refreshView = () => {
    appStore.refreshView()
}
</script>
