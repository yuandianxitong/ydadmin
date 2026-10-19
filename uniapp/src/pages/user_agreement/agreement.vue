<template>
  <view>正在打开协议...</view>
</template>

<script setup lang="ts">
import { onLoad } from '@dcloudio/uni-app'

// 兼容历史装修链接和已收藏的页面地址。
onLoad((options) => {
  const code = options?.code || 'user_agreement'
  uni.redirectTo({
    url: '/modules/agreement/pages/agreement?code=' + encodeURIComponent(code),
    fail: () => uni.showToast({ title: '协议页面打开失败，请返回重试', icon: 'none' }),
  })
})
</script>
