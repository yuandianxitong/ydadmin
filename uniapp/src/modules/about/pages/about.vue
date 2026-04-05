<template>
  <d-page :safe-area="true">
    <view class="about-page">
      <!-- Logo -->
      <view class="about-logo">
        <image class="logo-image" src="/static/logo.png" mode="aspectFit" />
      </view>

      <!-- App Name -->
      <text class="about-name">元点Admin</text>

      <!-- Version -->
      <text class="about-version">v1.0.0</text>

      <!-- Description -->
      <text class="about-desc">
        元点Admin 是一个基于 Vue3 + ThinkPHP8 的全栈管理系统框架，
        提供高效的前后端开发解决方案。
      </text>

      <!-- Info Cards -->
      <view class="info-card">
        <u-cell-group>
          <u-cell title="官方网站" value="dev007.cn" isLink @click="openWebsite" />
          <u-cell title="技术栈" value="Vue3 + ThinkPHP8" />
          <u-cell title="许可协议" value="MIT License" />
        </u-cell-group>
      </view>

      <!-- Copyright -->
      <view class="copyright">
        <text class="copyright-text">Copyright 2024 元点Admin</text>
      </view>
    </view>
  </d-page>
</template>

<script setup lang="ts">
function openWebsite() {
  uni.navigateTo({
    url: '/modules/webview/pages/webview?url=' + encodeURIComponent('https://dev007.cn'),
  })
}
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.about-page {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding-top: 60rpx;
}

.about-logo {
  width: 160rpx;
  height: 160rpx;
  border-radius: 32rpx;
  overflow: hidden;
  margin-bottom: 24rpx;
  box-shadow: 0 8rpx 24rpx rgba(41, 121, 255, 0.15);

  .logo-image {
    width: 100%;
    height: 100%;
  }
}

.about-name {
  font-size: 40rpx;
  font-weight: 700;
  color: $text-color;
  margin-bottom: 8rpx;
}

.about-version {
  font-size: 26rpx;
  color: $text-color-secondary;
  margin-bottom: 40rpx;
}

.about-desc {
  font-size: 26rpx;
  color: $text-color-secondary;
  text-align: center;
  line-height: 1.8;
  padding: 0 40rpx;
  margin-bottom: 60rpx;
}

.info-card {
  width: 100%;
  background: #ffffff;
  border-radius: 24rpx;
  overflow: hidden;
  box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.05);
  margin-bottom: 40rpx;
}

.copyright {
  padding: 40rpx 0;

  &-text {
    font-size: 22rpx;
    color: #cccccc;
  }
}
</style>
