<template>
  <view class="d-list-loader">
    <view v-if="loading" class="d-list-loader__loading">
      <wd-loading size="40rpx" />
      <text class="d-list-loader__text">加载中...</text>
    </view>
    <view v-else-if="finished && total > 0" class="d-list-loader__finished">
      <text class="d-list-loader__text">没有更多了</text>
    </view>
    <d-empty v-else-if="finished && total === 0" :text="emptyText" />
  </view>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  loading?: boolean
  finished?: boolean
  total?: number
  emptyText?: string
}>(), {
  loading: false,
  finished: false,
  total: 0,
  emptyText: '暂无数据',
})
</script>

<style lang="scss" scoped>
.d-list-loader {
  &__loading,
  &__finished {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 30rpx;
  }

  &__text {
    font-size: 24rpx;
    color: #999;
    margin-left: 10rpx;
  }
}
</style>
