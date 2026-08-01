<template>
  <div class="pv-ad" :style="layout === 'grid' ? { display:'grid', gridTemplateColumns:`repeat(${cols},1fr)`, gap:'4px' } : {}">
    <template v-if="items.length">
      <!-- 无图条目渲染占位块而非空 src 的 0 高 img，避免组件在画布上不可见被遗漏 -->
      <template v-for="(it, i) in items" :key="i">
        <img v-if="it.image" :src="it.image" class="pv-ad__img" />
        <div v-else class="pv-empty">图片广告</div>
      </template>
    </template>
    <div v-else class="pv-empty">图片广告</div>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ props: Record<string, any> }>()
const items = computed(() => props.props?.items || [])
const layout = computed(() => props.props?.layout || 'single')
const cols = computed(() => props.props?.columns || 2)
</script>
<style scoped>
.pv-ad { padding:0; }
.pv-ad__img { width:100%; display:block; }
/* 空态占位块 150px 高（与轮播图默认高度一致，画布形态稳定） */
.pv-empty { display:flex;align-items:center;justify-content:center;height:150px;color:#9aa4b2;font-size:12px;background:#eef1f6;border-radius:4px; }
</style>
