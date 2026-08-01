<template>
    <div class="plugin-stub">插件组件未启用</div>
</template>
<script setup lang="ts">
defineProps<{ type: string; props: Record<string, any>; meta?: any }>()
</script>
<style scoped>
.plugin-stub {
    padding: 12px;
    color: #999;
    font-size: 12px;
    text-align: center;
}
</style>
