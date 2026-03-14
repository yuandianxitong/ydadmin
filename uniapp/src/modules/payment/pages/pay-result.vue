<template>
  <d-page :safe-area="true">
    <d-pay-result :success="isSuccess" :message="resultMessage">
      <view class="result-actions">
        <wd-button type="primary" block @click="goHome" class="action-btn">
          返回首页
        </wd-button>
        <wd-button plain block @click="goOrderDetail" class="action-btn">
          查看订单
        </wd-button>
      </view>
    </d-pay-result>
  </d-page>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { usePayment } from '../composables/usePayment'

const { checkPayResult } = usePayment()

const isSuccess = ref(false)
const resultMessage = ref('')
const orderNo = ref('')

onLoad((query) => {
  orderNo.value = query?.order_no || ''
  isSuccess.value = query?.status === 'success'
  resultMessage.value = isSuccess.value ? '支付成功' : '支付失败'
})

onMounted(async () => {
  if (orderNo.value) {
    const paid = await checkPayResult(orderNo.value)
    isSuccess.value = paid
    resultMessage.value = paid ? '支付成功' : '支付失败'
  }
})

function goHome() {
  uni.reLaunch({ url: '/pages/index/index' })
}

function goOrderDetail() {
  if (orderNo.value) {
    uni.navigateBack({ delta: 2 })
  } else {
    uni.navigateBack()
  }
}
</script>

<style lang="scss" scoped>
.result-actions {
  display: flex;
  flex-direction: column;
  gap: 24rpx;
  padding: 40rpx 32rpx;

  .action-btn {
    border-radius: 16rpx !important;
    height: 88rpx !important;
    font-size: 30rpx !important;
  }
}
</style>
