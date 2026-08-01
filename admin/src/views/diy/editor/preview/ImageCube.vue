<template>
  <div class="pv-cube">
    <div v-for="(it, i) in items" :key="i" class="pv-cube__item" :style="itemStyle">
      <img v-if="it.image" :src="it.image" class="pv-cube__img" />
      <div v-else class="pv-cube__ph"></div>
    </div>
    <div v-if="!items.length" class="pv-empty">图片魔方</div>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ props: Record<string, any> }>()
const items = computed(() => props.props?.items || [])
const cols = computed(() => props.props?.cols || 2)
const itemStyle = computed(() => {
  // gap 单位 rpx：uniapp 侧 gap/2 rpx 落地，画布 px 需再 ÷2（即 gap/4），否则预览间距是真机 2 倍
  const g = (props.props?.gap || 0) / 4
  return { width: `${100 / (cols.value || 2)}%`, paddingLeft: `${g}px`, paddingRight: `${g}px` }
})
</script>
<style scoped>
.pv-cube { display:flex; flex-wrap:wrap; }
.pv-cube__item { box-sizing:border-box; }
/* 单元高对齐 C 端 diy-image-cube（200rpx ÷2 = 100px）；空态占位块 150px 高（与轮播图默认一致） */
.pv-cube__img { width:100%; height:100px; object-fit:cover; border-radius:4px; display:block; }
.pv-cube__ph { width:100%; height:100px; background:#e3e7ef; border-radius:4px; }
.pv-empty { width:100%; display:flex; align-items:center; justify-content:center; height:150px; color:#9aa4b2; font-size:12px; background:#eef1f6; border-radius:4px; }
</style>
