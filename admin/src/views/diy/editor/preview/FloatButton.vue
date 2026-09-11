<template>
  <div class="pv-float" :class="props.props?.position === 'left-bottom' ? 'is-left' : 'is-right'">
    <!-- icon 与 text 可共存（与 uniapp diy-float-button 一致：图标在上文字在下） -->
    <div v-for="(it, i) in items" :key="i" class="pv-float__btn">
      <img v-if="it.icon" :src="it.icon" class="pv-float__icon" />
      <span v-if="it.text" class="pv-float__t">{{ it.text }}</span>
      <span v-if="!it.icon && !it.text" class="pv-float__t">按钮</span>
    </div>
    <div v-if="!items.length" class="pv-float__btn pv-float__t">悬浮</div>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ props: Record<string, any> }>()
const items = computed(() => props.props?.items || [])
</script>
<style scoped>
/* bottom 120rpx=60px、直径 96rpx=48px，与 uniapp diy-float-button 换算对齐 */
.pv-float { position:absolute; bottom:60px; display:flex; flex-direction:column; gap:8px; z-index:5; }
.pv-float.is-right { right:12px; }
.pv-float.is-left { left:12px; }
.pv-float__btn { width:48px; height:48px; border-radius:50%; background:rgba(0,0,0,.6); color:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center; }
.pv-float__icon { width:20px; height:20px; }
.pv-float__t { font-size:10px; }
</style>
