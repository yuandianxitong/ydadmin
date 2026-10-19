<template>
  <d-page>
    <view v-if="loading" class="agreement-state">协议加载中...</view>
    <view v-else-if="agreement?.content" class="agreement-content">
      <text class="agreement-title">{{ agreement.title }}</text>
      <rich-text :nodes="agreement.content" />
    </view>
    <view v-else class="agreement-state">
      <text>{{ errorMessage || '协议内容尚未发布' }}</text>
      <u-button v-if="errorMessage" size="small" class="retry-btn" @click="loadAgreement">重新加载</u-button>
    </view>
  </d-page>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { agreementApi, type AgreementItem } from '@/api/agreement'

const agreement = ref<AgreementItem | null>(null)
const loading = ref(true)
const errorMessage = ref('')
const agreementCode = ref('user_agreement')

async function loadAgreement() {
  loading.value = true
  errorMessage.value = ''
  try {
    agreement.value = await agreementApi.getByCode(agreementCode.value)
    if (agreement.value?.title) {
      uni.setNavigationBarTitle({ title: agreement.value.title })
    }
  } catch {
    agreement.value = null
    errorMessage.value = '协议加载失败，请重试'
  } finally {
    loading.value = false
  }
}

onLoad((options) => {
  agreementCode.value = options?.code || 'user_agreement'
  loadAgreement()
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
  overflow-wrap: anywhere;
}

.agreement-title {
  display: block;
  margin-bottom: 28rpx;
  font-size: 36rpx;
  font-weight: 600;
}

.agreement-state {
  min-height: 40vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 24rpx;
  font-size: 28rpx;
  color: #909399;
}

.retry-btn {
  width: 240rpx;
}
</style>
