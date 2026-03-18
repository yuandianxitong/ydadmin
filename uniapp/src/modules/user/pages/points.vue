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
          <view
            v-for="item in list"
            :key="item.id"
            class="log-item"
          >
            <view class="log-item__left">
              <text class="log-item__type">{{ item.type }}</text>
              <text class="log-item__remark">{{ item.remark || '无备注' }}</text>
            </view>
            <view class="log-item__right">
              <text
                class="log-item__points"
                :class="item.points >= 0 ? 'is-income' : 'is-expense'"
              >
                {{ item.points >= 0 ? '+' : '' }}{{ item.points }}
              </text>
              <text class="log-item__time">{{ item.created_at }}</text>
            </view>
          </view>

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

.log-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24rpx 0;
  border-bottom: 1rpx solid $border-color;

  &:last-child {
    border-bottom: none;
  }

  &__left {
    flex: 1;
  }

  &__type {
    display: block;
    font-size: 28rpx;
    color: $text-color;
    font-weight: 500;
    margin-bottom: 8rpx;
  }

  &__remark {
    display: block;
    font-size: 24rpx;
    color: $text-color-secondary;
  }

  &__right {
    text-align: right;
    flex-shrink: 0;
    margin-left: 20rpx;
  }

  &__points {
    display: block;
    font-size: 30rpx;
    font-weight: 600;
    margin-bottom: 8rpx;

    &.is-income {
      color: $success-color;
    }

    &.is-expense {
      color: $danger-color;
    }
  }

  &__time {
    display: block;
    font-size: 22rpx;
    color: $text-color-secondary;
  }
}
</style>
