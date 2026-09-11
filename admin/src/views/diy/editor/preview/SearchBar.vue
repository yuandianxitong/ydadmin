<template>
  <div class="pv-search">
    <!-- radius 单位 rpx，画布 ÷2 换算（与 uniapp diy-search-bar 对齐） -->
    <div class="pv-search__box" :style="{ borderRadius: (props.props?.radius ?? 20) / 2 + 'px', background: props.props?.bg_color || '#f5f5f5' }">
      <span class="pv-search__ph">{{ props.props?.placeholder || '搜索' }}</span>
    </div>
  </div>
</template>
<script setup lang="ts">
const props = defineProps<{ props: Record<string, any> }>()
</script>
<style scoped>
.pv-search { padding:8px 12px; }
.pv-search__box { height:32px; display:flex; align-items:center; padding:0 12px; }
.pv-search__ph { color:#999; font-size:13px; }
</style>
