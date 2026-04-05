<template>
  <d-page :safe-area="true">
    <view class="edit-profile-page">
      <!-- 头像上传 -->
      <view class="section-card">
        <d-avatar-upload v-model="form.avatar" />
      </view>

      <!-- 基本信息 -->
      <view class="section-card">
        <u-cell-group>
          <u-cell title="昵称">
            <template #default>
              <u--input
                v-model="form.nickname"
                placeholder="请输入昵称"
                border="none"
                inputAlign="right"
                class="cell-input"
              />
            </template>
          </u-cell>
          <u-cell title="性别" isLink :value="genderLabel" @click="showGenderPicker = true" />
          <u-cell title="生日" isLink :value="birthdayLabel" @click="showBirthdayPicker = true" />
        </u-cell-group>
      </view>

      <!-- Gender Picker -->
      <u-picker
        :show="showGenderPicker"
        :columns="[genderOptions]"
        keyName="label"
        @confirm="onGenderConfirm"
        @cancel="showGenderPicker = false"
      />

      <!-- Birthday Picker -->
      <u-datetime-picker
        :show="showBirthdayPicker"
        mode="date"
        :maxDate="maxDate"
        @confirm="onBirthdayConfirm"
        @cancel="showBirthdayPicker = false"
      />

      <!-- 保存按钮 -->
      <u-button
        block
        :loading="loading"
        :disabled="loading"
        class="save-btn"
        @click="handleSave"
      >
        保存
      </u-button>
    </view>
  </d-page>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
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
const showGenderPicker = ref(false)
const showBirthdayPicker = ref(false)

const form = reactive({
  avatar: '',
  nickname: '',
  gender: 0,
  birthday: '' as string | number,
})

const genderLabel = computed(() => {
  const found = genderOptions.find(o => o.value === form.gender)
  return found ? found.label : '请选择性别'
})

const birthdayLabel = computed(() => {
  const str = formatBirthday(form.birthday)
  return str || '请选择生日'
})

// 时间戳转 YYYY-MM-DD
function formatBirthday(val: string | number): string {
  if (!val) return ''
  const d = typeof val === 'number' ? new Date(val) : new Date(val)
  if (isNaN(d.getTime())) return ''
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

// 日期字符串转时间戳（供 u-datetime-picker 使用）
function parseBirthday(val: string): number {
  if (!val) return 0
  const d = new Date(val.replace(/-/g, '/'))
  return isNaN(d.getTime()) ? 0 : d.getTime()
}

function onGenderConfirm(e: { value: any[]; indexs: number[] }) {
  form.gender = e.value[0].value
  showGenderPicker.value = false
}

function onBirthdayConfirm(e: { value: number }) {
  form.birthday = e.value
  showBirthdayPicker.value = false
}

onMounted(async () => {
  try {
    const profile = await loadProfile()
    form.avatar = profile.avatar || ''
    form.nickname = profile.nickname || ''
    form.gender = profile.gender ?? 0
    form.birthday = parseBirthday(profile.birthday || '')
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
    birthday: formatBirthday(form.birthday),
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
