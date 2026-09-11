<template>
  <!-- 后台预览租户自有富文本内容（self-XSS 范围）。端上(uniapp)用 <rich-text> 安全渲染；
       若未来 PC/H5 用 v-html 渲染同字段，需在渲染端做 DOMPurify 净化。 -->
  <div class="pv-rich" v-html="props.props?.content || '<span style=\'color:#9aa4b2\'>富文本</span>'"></div>
</template>
<script setup lang="ts">
const props = defineProps<{ props: Record<string, any> }>()
</script>
<style scoped>.pv-rich { padding:10px; }</style>
