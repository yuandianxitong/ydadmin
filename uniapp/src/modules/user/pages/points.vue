<template>
  <d-page :safe-area="true">
    <view class="points-page">
      <!-- Points Display -->
      <view class="points-header">
        <text class="points-label">当前积分</text>
        <text class="points-amount">{{ points }}</text>
      </view>

      <!-- Points Log List -->
      <view class="log-section">
        <text class="section-title">积分明细</text>
        <scroll-view
          scroll-y
          class="log-scroll"
          @scrolltolower="getList"
        >
          <d-ledger-list :items="list" value-key="points" value-mode="integer" />

          <d-list-loader
            :loading="loading"
            :finished="finished"
            :total="total"
            empty-text="暂无积分记录"
          />
        </scroll-view>
      </view>
    </view>
  </d-page>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { userApi, type PointsLogItem } from '@/api/user'
import { usePaging } from '@/hooks/usePaging'

const points = ref(0)

const { list, loading, finished, total, getList } = usePaging<PointsLogItem>({
  fetchFun: (params) => userApi.getPointsLogs(params),
})

async function loadPoints() {
  try {
    const res = await userApi.getPoints()
    points.value = res.points || 0
  } catch {
    // ignore
  }
}

onMounted(() => {
  loadPoints()
  getList()
})
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.points-page {
  padding: 0;
}

.points-header {
  background: linear-gradient(135deg, #ff9900, #f59e0b);
  border-radius: 24rpx;
  padding: 48rpx 40rpx;
  margin-bottom: 24rpx;
  text-align: center;

  .points-label {
    display: block;
    font-size: 26rpx;
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 16rpx;
  }

  .points-amount {
    display: block;
    font-size: 72rpx;
    font-weight: 700;
    color: #ffffff;
  }
}

.section-title {
  display: block;
  font-size: 30rpx;
  font-weight: 600;
  color: $text-color;
  margin-bottom: 24rpx;
}

.log-section {
  background: #ffffff;
  border-radius: 24rpx;
  padding: 32rpx;
  box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.05);
}

.log-scroll {
  max-height: 1000rpx;
}
</style>
