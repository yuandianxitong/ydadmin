<template>
  <view class="diy-divider" :style="dividerStyle"></view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ props: Record<string, any> }>()
const dividerStyle = computed(() => {
  const p = props.props || {}
  const h = (p.height || 20) + 'rpx'
  if (p.type === 'line') return { height: '1px', backgroundColor: p.color || '#eee', margin: `${h} 0` }
  return { height: h }
})
</script>

<style lang="scss" scoped>
.diy-divider {
  width: 100%;
}
</style>
