<template>
  <!-- 布局对齐 uniapp diy-nav-grid：flex 等分无列间距、图标 80rpx=40px aspectFit 无圆角 -->
  <div class="pv-nav">
    <div v-for="(it, i) in items" :key="i" class="pv-nav__item" :style="{ width: itemWidth }">
      <div class="pv-nav__icon-wrap">
        <img v-if="it.icon" :src="it.icon" class="pv-nav__icon" />
        <div v-else class="pv-nav__icon pv-ph"></div>
        <span v-if="it.badge_key" class="pv-badge-dot" />
      </div>
      <span class="pv-nav__text">{{ it.text || '导航' }}</span>
    </div>
    <div v-if="!items.length" class="pv-empty">图文导航</div>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ props: Record<string, any> }>()
const items = computed(() => props.props?.items || [])
const cols = computed(() => props.props?.columns || 4)
const itemWidth = computed(() => `${100 / (cols.value || 4)}%`)
</script>
<style scoped>
.pv-nav { display:flex; flex-wrap:wrap; padding:10px; box-sizing:border-box; }
.pv-nav__item { display:flex; flex-direction:column; align-items:center; padding:6px 0; box-sizing:border-box; }
.pv-nav__icon-wrap { position:relative; }
.pv-nav__icon { width:40px;height:40px;object-fit:contain; }
.pv-ph { background:#e3e7ef; border-radius:8px; }
.pv-badge-dot { position:absolute; top:-2px; right:-4px; width:8px; height:8px; border-radius:50%; background:#f56c6c; }
.pv-nav__text { font-size:11px;color:#555;margin-top:4px; }
.pv-empty { width:100%;text-align:center;color:#9aa4b2;font-size:12px;padding:10px; }
</style>
