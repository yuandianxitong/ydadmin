<template>
  <!-- 注意：微信小程序的 <rich-text> 对 HTML 字符串支持有限（部分标签会被静默丢弃）。
       MVP 仅保证 H5/App 完整渲染；如需小程序完整富文本，后续改为 nodes 数组解析。 -->
  <view class="diy-rich-text"><rich-text :nodes="(props.props?.content) || ''" /></view>
</template>

<script setup lang="ts">
const props = defineProps<{ props: Record<string, any> }>()
</script>

<style scoped>.diy-rich-text { padding: 20rpx; }</style>
