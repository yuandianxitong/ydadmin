<template>
  <div class="pv-title" :style="{ justifyContent: (props.props?.align === 'center') ? 'center' : 'flex-start' }">
    <span class="pv-title__t">{{ props.props?.title || '标题' }}</span>
    <span v-if="props.props?.subtitle" class="pv-title__s">{{ props.props?.subtitle }}</span>
    <span v-if="props.props?.more_link" class="pv-title__more">{{ props.props?.more_text || '更多' }}</span>
  </div>
</template>
<script setup lang="ts">
const props = defineProps<{ props: Record<string, any> }>()
</script>
<style scoped>
.pv-title { display:flex; align-items:baseline; padding:10px; }
.pv-title__t { font-size:15px;font-weight:600;color:#222; }
.pv-title__s { font-size:12px;color:#999;margin-left:8px; }
.pv-title__more { font-size:12px;color:#999;margin-left:auto; }
</style>
