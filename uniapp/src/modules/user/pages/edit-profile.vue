<template>
  <d-page :safe-area="true">
    <view class="edit-profile-page">
      <!-- 头像上传 -->
      <view class="section-card">
        <d-avatar-upload v-model="form.avatar" />
      </view>

      <!-- 基本信息 -->
      <view class="section-card">
        <wd-cell-group>
          <wd-cell title="昵称">
            <template #default>
              <wd-input
                v-model="form.nickname"
                placeholder="请输入昵称"
                no-border
                align-right
                class="cell-input"
              />
            </template>
          </wd-cell>
          <wd-cell title="性别">
            <template #default>
              <wd-picker
                v-model="form.gender"
                :columns="genderOptions"
                align-right
                placeholder="请选择性别"
                @confirm="onGenderConfirm"
              />
            </template>
          </wd-cell>
          <wd-cell title="生日">
            <template #default>
              <wd-datetime-picker
                v-model="form.birthday"
                type="date"
                align-right
                placeholder="请选择生日"
                :max-date="maxDate"
              />
            </template>
          </wd-cell>
        </wd-cell-group>
      </view>

      <!-- 保存按钮 -->
      <wd-button
        block
        :loading="loading"
        :disabled="loading"
        class="save-btn"
        @click="handleSave"
      >
        保存
      </wd-button>
    </view>
  </d-page>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useUser } from '../composables/useUser'
import { useUserStore } from '@/store/user.store'
import { useAppStore } from '@/store/app.store'

const userStore = useUserStore()
const appStore = useAppStore()
const { loading, loadProfile, updateProfile } = useUser()

const genderOptions = [
  { label: '未知', value: 0 },
  { label: '男', value: 1 },
  { label: '女', value: 2 },
]

const maxDate = new Date().getTime()

const form = reactive({
  avatar: '',
  nickname: '',
  gender: 0,
  birthday: '',
})

function onGenderConfirm(val: { value: number }) {
  form.gender = val.value
}

onMounted(async () => {
  try {
    const profile = await loadProfile()
    form.avatar = appStore.getImageUrl(profile.avatar || '')
    form.nickname = profile.nickname || ''
    form.gender = profile.gender ?? 0
    form.birthday = profile.birthday || ''
  } catch {
    // error handled
  }
})

async function handleSave() {
  if (!form.nickname.trim()) {
    uni.showToast({ title: '请输入昵称', icon: 'none' })
    return
  }

  await updateProfile({
    avatar: form.avatar,
    nickname: form.nickname,
    gender: form.gender,
    birthday: form.birthday,
  })
}
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.edit-profile-page {
  padding: 0;
}

.section-card {
  background: #ffffff;
  border-radius: 24rpx;
  overflow: hidden;
  margin-bottom: 24rpx;
  box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.05);
}

.cell-input {
  width: 100%;
  text-align: right;
}

.save-btn {
  border-radius: 16rpx !important;
  height: 96rpx !important;
  font-size: 32rpx !important;
  margin-top: 12rpx;
}
</style>
