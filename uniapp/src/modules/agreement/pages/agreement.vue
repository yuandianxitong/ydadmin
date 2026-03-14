<template>
  <d-page>
    <view v-if="agreement" class="agreement-content">
      <rich-text :nodes="agreement.content" />
    </view>
    <d-empty v-else-if="!loading" text="协议内容不存在" />
  </d-page>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { agreementApi, type AgreementItem } from '@/api/agreement'

const agreement = ref<AgreementItem | null>(null)
const loading = ref(true)

onLoad(async (options) => {
  const code = options?.code || ''
  if (code) {
    try {
      agreement.value = await agreementApi.getByCode(code)
      if (agreement.value?.title) {
        uni.setNavigationBarTitle({ title: agreement.value.title })
      }
    } catch {
      uni.showToast({ title: '加载失败', icon: 'none' })
    }
  }
  loading.value = false
})
</script>

<style lang="scss" scoped>
.agreement-content {
  background: #ffffff;
  border-radius: 16rpx;
  padding: 32rpx 28rpx;
  font-size: 28rpx;
  color: #333;
  line-height: 1.8;
  min-height: 50vh;
}
</style>
