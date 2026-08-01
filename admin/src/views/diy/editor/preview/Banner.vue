<template>
  <!-- 适应宽度：有图时按原图比例撑开；无图用 height(rpx÷2) 占位 -->
  <div class="pv-banner" :style="boxStyle">
    <img v-if="firstImg" :src="firstImg" class="pv-banner__img" alt="" />
    <div v-else class="pv-empty">轮播图</div>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { useAppStore } from '@/store/modules/app.store'

const props = defineProps<{ props: Record<string, any> }>()
const appStore = useAppStore()
const firstImg = computed(() => {
  const url = props.props?.items?.[0]?.image || ''
  return url ? (appStore.getImageUrl?.(url) || url) : ''
})
const boxStyle = computed(() => {
  if (firstImg.value) return undefined
  return { height: ((props.props?.height || 300) / 2) + 'px' }
})
</script>
<style scoped>
.pv-banner { width: 100%; overflow: hidden; }
.pv-banner__img { width: 100%; height: auto; display: block; vertical-align: top; }
.pv-empty { display:flex;align-items:center;justify-content:center;height:100%;color:#9aa4b2;font-size:12px;background:#eef1f6; }
</style>
