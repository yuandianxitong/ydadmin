<!-- 文件：/src/layout/index.vue -->
<template>
    <div class="layout-default flex h-screen w-full">
        <div class="app-aside">
            <layout-sidebar />
        </div>

        <!-- 主内容 -->
        <div class="flex-1 flex flex-col min-w-0">
            <div class="app-header border-b border-b-[var(--color-divider)]"><layout-header /></div>
            <div class="app-main flex-1 min-h-0"><layout-main /></div>
        </div>
    </div>
</template>

<script setup lang="ts">
import LayoutHeader from './components/header/index.vue'
import LayoutMain from './components/main.vue'
import LayoutSidebar from './components/sidebar/index.vue'
</script>

<style></style>
