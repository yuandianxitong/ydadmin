<template>
  <d-page :safe-area="true">
    <view class="settings-page">
      <!-- 存储设置 -->
      <view class="section-card">
        <wd-cell-group>
          <wd-cell
            title="清除缓存"
            is-link
            :value="cacheSize"
            @click="handleClearCache"
          >
            <template #icon>
              <wd-icon name="delete" size="40rpx" color="#fa3534" class="cell-icon" />
            </template>
          </wd-cell>
        </wd-cell-group>
      </view>

      <!-- 关于 -->
      <view class="section-card">
        <wd-cell-group>
          <wd-cell title="当前版本" :value="version">
            <template #icon>
              <wd-icon name="info-circle" size="40rpx" color="#2979ff" class="cell-icon" />
            </template>
          </wd-cell>
          <wd-cell
            title="隐私政策"
            is-link
            @click="openPrivacy"
          >
            <template #icon>
              <wd-icon name="secured" size="40rpx" color="#19be6b" class="cell-icon" />
            </template>
          </wd-cell>
          <wd-cell
            title="用户协议"
            is-link
            @click="openTerms"
          >
            <template #icon>
              <wd-icon name="file-text" size="40rpx" color="#ff9900" class="cell-icon" />
            </template>
          </wd-cell>
        </wd-cell-group>
      </view>
    </view>
  </d-page>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAppStore } from '@/store/app.store'

const appStore = useAppStore()

const version = ref('v1.0.0')
const cacheSize = ref('计算中...')

onMounted(() => {
  calculateCache()
})

function calculateCache() {
  try {
    const info = uni.getStorageInfoSync()
    const sizeKB = info.currentSize || 0
    if (sizeKB < 1024) {
      cacheSize.value = `${sizeKB} KB`
    } else {
      cacheSize.value = `${(sizeKB / 1024).toFixed(1)} MB`
    }
  } catch {
    cacheSize.value = '未知'
  }
}

function handleClearCache() {
  uni.showModal({
    title: '提示',
    content: '确认清除缓存？',
    success: (res) => {
      if (res.confirm) {
        try {
          // Clear all storage except token
          const token = uni.getStorageSync('dev007_token')
          uni.clearStorageSync()
          if (token) {
            uni.setStorageSync('dev007_token', token)
          }
          // Reset app store config
          appStore.isConfigLoaded = false
          cacheSize.value = '0 KB'
          uni.showToast({ title: '缓存已清除' })
        } catch {
          uni.showToast({ title: '清除失败', icon: 'none' })
        }
      }
    },
  })
}

function openPrivacy() {
  const url = '/modules/webview/pages/webview?url=' + encodeURIComponent('https://example.com/privacy') + '&title=' + encodeURIComponent('隐私政策')
  uni.navigateTo({ url })
}

function openTerms() {
  const url = '/modules/webview/pages/webview?url=' + encodeURIComponent('https://example.com/terms') + '&title=' + encodeURIComponent('用户协议')
  uni.navigateTo({ url })
}
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.settings-page {
  padding: 0;
}

.section-card {
  background: #ffffff;
  border-radius: 24rpx;
  overflow: hidden;
  margin-bottom: 24rpx;
  box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.05);

  .cell-icon {
    margin-right: 16rpx;
  }
}
</style>
