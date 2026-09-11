<template>
  <div class="pv-notice">
    <img v-if="props.props?.icon" :src="props.props.icon" class="pv-notice__icon" />
    <span class="pv-notice__tag">公告</span>
    <span class="pv-notice__text">{{ items[0]?.text || '公告内容' }}</span>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ props: Record<string, any> }>()
const items = computed(() => props.props?.items || [])
</script>
<style scoped>
.pv-notice { display:flex; align-items:center; gap:8px; padding:8px 12px; background:#fffbe6; }
.pv-notice__icon { width:16px; height:16px; object-fit:contain; flex:none; }
.pv-notice__tag { font-size:11px; color:#fff; background:#e6a23c; border-radius:3px; padding:1px 5px; flex:none; }
.pv-notice__text { font-size:12px; color:#8a6d3b; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
</style>
