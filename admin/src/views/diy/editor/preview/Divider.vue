<template>
  <div class="pv-divider" :style="dividerStyle"></div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ props: Record<string, any> }>()
const dividerStyle = computed(() => {
  const p = props.props || {}
  // height 单位 rpx（750 设计稿），画布 375 宽 ÷2 换算，与 uniapp diy-divider 对齐
  const h = (p.height || 20) / 2 + 'px'
  if (p.type === 'line') return { height: '1px', backgroundColor: p.color || '#eee', margin: `${h} 0` }
  return { height: h }
})
</script>
<style scoped>.pv-divider { width:100%; }</style>
