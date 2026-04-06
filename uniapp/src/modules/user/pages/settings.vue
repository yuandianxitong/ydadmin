<template>
  <d-page :safe-area="true">
    <view class="settings-page">
      <!-- 存储设置 -->
      <view class="section-card">
        <u-cell-group>
          <u-cell
            title="清除缓存"
            isLink
            :value="cacheSize"
            @click="handleClearCache"
          >
            <template #icon>
              <text class="iconfont icon-trash3 cell-icon" style="color: #fa3534" />
            </template>
          </u-cell>
          <u-cell
            title="检查更新"
            isLink
            @click="handleCheckUpdate"
          >
            <template #icon>
              <text class="iconfont icon-phone-vibrate-fill cell-icon" style="color: #2979ff" />
            </template>
          </u-cell>
        </u-cell-group>
      </view>

      <!-- 关于 -->
      <view class="section-card">
        <u-cell-group>
          <u-cell title="当前版本" :value="version">
            <template #icon>
              <text class="iconfont icon-question-circle cell-icon" style="color: #2979ff" />
            </template>
          </u-cell>
          <u-cell
            title="隐私政策"
            isLink
            @click="openPrivacy"
          >
            <template #icon>
              <text class="iconfont icon-shield-shaded cell-icon" style="color: #19be6b" />
            </template>
          </u-cell>
          <u-cell
            title="用户协议"
            isLink
            @click="openTerms"
          >
            <template #icon>
              <text class="iconfont icon-file-earmark-text cell-icon" style="color: #ff9900" />
            </template>
          </u-cell>
        </u-cell-group>
      </view>

      <!-- 退出登录 -->
      <view v-if="userStore.isLoggedIn" class="logout-area">
        <u-button
          type="error"
          plain
          block
          class="logout-btn"
          @click="handleLogout"
        >
          退出登录
        </u-button>
      </view>
    </view>
  </d-page>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAppStore } from '@/store/app.store'
import { useUserStore } from '@/store/user.store'
import { useVersionCheck } from '@/hooks/useVersionCheck'

const appStore = useAppStore()
const userStore = useUserStore()
const { checking, checkUpdate } = useVersionCheck()

// 从 uniapp 运行时读取当前版本号，统一展示
const version = ref(getCurrentVersionText())
const cacheSize = ref('计算中...')

function getCurrentVersionText(): string {
  try {
    const appBaseInfo = uni.getAppBaseInfo?.() as any
    const v = appBaseInfo?.appVersion
    return v ? `v${v}` : 'v1.0.0'
  } catch {
    return 'v1.0.0'
  }
}

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
          appStore.resetConfig()
          cacheSize.value = '0 KB'
          uni.showToast({ title: '缓存已清除' })
        } catch {
          uni.showToast({ title: '清除失败', icon: 'none' })
        }
      }
    },
  })
}

async function handleCheckUpdate() {
  if (checking.value) return
  uni.showLoading({ title: '检查中...' })
  try {
    await checkUpdate(false)
  } finally {
    uni.hideLoading()
  }
}

function handleLogout() {
  uni.showModal({
    title: '提示',
    content: '确认退出登录？',
    success: (res) => {
      if (res.confirm) {
        userStore.logout({ redirect: false })
        uni.switchTab({ url: '/pages/index/index' })
      }
    },
  })
}

function openPrivacy() {
  uni.navigateTo({ url: '/modules/agreement/pages/agreement?code=privacy_policy' })
}

function openTerms() {
  uni.navigateTo({ url: '/modules/agreement/pages/agreement?code=user_agreement' })
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
    font-size: 40rpx;
    margin-right: 16rpx;
  }
}

.logout-area {
  padding: 40rpx 0;

  .logout-btn {
    border-radius: 16rpx !important;
    height: 96rpx !important;
    font-size: 32rpx !important;
  }
}
</style>
