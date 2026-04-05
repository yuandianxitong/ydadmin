<template>
  <d-page :safe-area="true">
    <view class="change-password-page">
      <!-- 提示信息 -->
      <view class="hint-card">
        <view class="i-ri-information-line" style="font-size: 36rpx; color: #2979ff" />
        <text class="hint-text">修改密码后将自动退出登录，请重新登录</text>
      </view>

      <!-- 表单卡片 -->
      <view class="form-card">
        <!-- 旧密码 -->
        <view class="input-group">
          <view class="i-ri-lock-line input-prefix-icon" style="font-size: 36rpx; color: #999" />
          <u--input
            v-model="form.old_password"
            :password="true"
            placeholder="请输入当前密码"
            clearable
            border="none"
            class="custom-input"
          />
        </view>

        <view class="divider" />

        <!-- 新密码 -->
        <view class="input-group">
          <view class="i-ri-lock-line input-prefix-icon" style="font-size: 36rpx; color: #999" />
          <u--input
            v-model="form.new_password"
            :password="true"
            placeholder="请输入新密码（6-20位）"
            clearable
            border="none"
            class="custom-input"
          />
        </view>

        <view class="divider" />

        <!-- 确认新密码 -->
        <view class="input-group">
          <view class="i-ri-lock-line input-prefix-icon" style="font-size: 36rpx; color: #999" />
          <u--input
            v-model="form.confirm_password"
            :password="true"
            placeholder="请再次输入新密码"
            clearable
            border="none"
            class="custom-input"
          />
        </view>
      </view>

      <!-- 提交按钮 -->
      <u-button
        block
        :loading="loading"
        :disabled="loading"
        class="submit-btn"
        @click="handleSubmit"
      >
        确认修改
      </u-button>
    </view>
  </d-page>
</template>

<script setup lang="ts">
import { reactive } from 'vue'
import { useUser } from '../composables/useUser'
import { isPassword } from '@/utils/validate'

const { loading, changePassword } = useUser()

const form = reactive({
  old_password: '',
  new_password: '',
  confirm_password: '',
})

async function handleSubmit() {
  if (!form.old_password) {
    uni.showToast({ title: '请输入当前密码', icon: 'none' })
    return
  }
  if (!isPassword(form.new_password)) {
    uni.showToast({ title: '新密码长度6-20位', icon: 'none' })
    return
  }
  if (form.new_password !== form.confirm_password) {
    uni.showToast({ title: '两次密码输入不一致', icon: 'none' })
    return
  }
  if (form.old_password === form.new_password) {
    uni.showToast({ title: '新密码不能与当前密码相同', icon: 'none' })
    return
  }

  await changePassword({
    old_password: form.old_password,
    new_password: form.new_password,
  })
}
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.change-password-page {
  padding: 0;
}

.hint-card {
  display: flex;
  align-items: center;
  background: #eef4ff;
  border-radius: 16rpx;
  padding: 24rpx;
  margin-bottom: 24rpx;

  .hint-text {
    font-size: 26rpx;
    color: $primary-color;
    margin-left: 12rpx;
    flex: 1;
  }
}

.form-card {
  background: #ffffff;
  border-radius: 24rpx;
  padding: 8rpx 0;
  margin-bottom: 40rpx;
  box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.05);
}

.input-group {
  padding: 8rpx 24rpx;
  display: flex;
  align-items: center;

  .input-prefix-icon {
    flex-shrink: 0;
    margin-right: 12rpx;
  }

  .custom-input {
    flex: 1;
    background: transparent;
  }
}

.divider {
  height: 1rpx;
  background: $border-color;
  margin: 0 32rpx;
}

.submit-btn {
  border-radius: 16rpx !important;
  height: 96rpx !important;
  font-size: 32rpx !important;
}
</style>
