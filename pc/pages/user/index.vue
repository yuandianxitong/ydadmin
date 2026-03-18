<template>
  <div class="mx-auto max-w-800px px-6 py-10">
    <h1 class="text-2xl font-bold text-gray-900 mb-8">个人中心</h1>

    <div v-if="loading" class="text-center py-10 text-gray-400">加载中...</div>
    <template v-else-if="profile">
      <!-- 个人信息 -->
      <div class="card p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4">基本信息</h3>
        <form @submit.prevent="handleUpdateProfile">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm text-gray-600 mb-1">昵称</label>
              <input
                v-model="profileForm.nickname"
                type="text"
                class="form-input"
              />
            </div>
            <div>
              <label class="block text-sm text-gray-600 mb-1">手机号</label>
              <input :value="profile.mobile" type="text" disabled class="form-input bg-gray-50 text-gray-400 !border-gray-200" />
            </div>
            <div>
              <label class="block text-sm text-gray-600 mb-1">性别</label>
              <select
                v-model="profileForm.gender"
                class="form-input"
              >
                <option :value="0">未知</option>
                <option :value="1">男</option>
                <option :value="2">女</option>
              </select>
            </div>
            <div>
              <label class="block text-sm text-gray-600 mb-1">生日</label>
              <input
                v-model="profileForm.birthday"
                type="date"
                class="form-input"
              />
            </div>
          </div>
          <div class="mt-6">
            <button type="submit" :disabled="saving" class="btn-primary text-sm">
              {{ saving ? '保存中...' : '保存修改' }}
            </button>
          </div>
        </form>
      </div>

      <!-- 修改密码 -->
      <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4">修改密码</h3>
        <form @submit.prevent="handleChangePassword">
          <div class="max-w-400px flex flex-col gap-4">
            <div>
              <label class="block text-sm text-gray-600 mb-1">当前密码</label>
              <input
                v-model="passwordForm.old_password"
                type="password"
                class="form-input"
              />
            </div>
            <div>
              <label class="block text-sm text-gray-600 mb-1">新密码</label>
              <input
                v-model="passwordForm.new_password"
                type="password"
                class="form-input"
              />
            </div>
          </div>
          <div class="mt-6">
            <button type="submit" :disabled="changingPwd" class="btn-primary text-sm">
              {{ changingPwd ? '修改中...' : '修改密码' }}
            </button>
          </div>
        </form>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { useMessage } from 'naive-ui'
import { userApi } from '~/api/user'
import type { UserInfo } from '~/api/auth'

definePageMeta({ middleware: 'auth' })

const message = useMessage()
const profile = ref<UserInfo | null>(null)
const loading = ref(true)
const saving = ref(false)
const changingPwd = ref(false)

const profileForm = reactive({ nickname: '', gender: 0, birthday: '' })
const passwordForm = reactive({ old_password: '', new_password: '' })

onMounted(async () => {
  try {
    const res = await userApi.getProfile()
    if (res.code === 200) {
      profile.value = res.data
      profileForm.nickname = res.data.nickname || ''
      profileForm.gender = res.data.gender || 0
      profileForm.birthday = res.data.birthday || ''
    }
  } finally {
    loading.value = false
  }
})

async function handleUpdateProfile() {
  saving.value = true
  try {
    const res = await userApi.updateProfile(profileForm)
    if (res.code === 200) {
      message.success('保存成功')
    } else {
      message.error(res.message || '保存失败')
    }
  } finally {
    saving.value = false
  }
}

async function handleChangePassword() {
  if (!passwordForm.old_password || !passwordForm.new_password) return
  changingPwd.value = true
  try {
    const res = await userApi.changePassword(passwordForm)
    if (res.code === 200) {
      message.success('密码修改成功')
      passwordForm.old_password = ''
      passwordForm.new_password = ''
    } else {
      message.error(res.message || '修改失败')
    }
  } finally {
    changingPwd.value = false
  }
}
</script>
