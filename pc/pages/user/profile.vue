<template>
  <div>
    <h2 class="text-xl font-bold text-gray-900 mb-6">个人资料</h2>

    <div v-if="loading" class="text-center py-10 text-gray-400">加载中...</div>
    <div v-else class="card p-6">
      <form @submit.prevent="handleUpdateProfile">
        <div class="grid grid-cols-2 gap-4 max-w-600px">
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
            <input
              :value="profile?.mobile"
              type="text"
              disabled
              class="form-input bg-gray-50 text-gray-400 !border-gray-200"
            />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">性别</label>
            <select v-model="profileForm.gender" class="form-input">
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
  </div>
</template>

<script setup lang="ts">
import { useMessage } from 'naive-ui'
import { userApi } from '~/api/user'
import type { UserInfo } from '~/api/auth'

const message = useMessage()
const refreshUserInfo = inject<() => Promise<void>>('refreshUserInfo')

const profile = ref<UserInfo | null>(null)
const loading = ref(true)
const saving = ref(false)

const profileForm = reactive({ nickname: '', gender: 0, birthday: '' })

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
      refreshUserInfo?.()
    } else {
      message.error(res.message || '保存失败')
    }
  } finally {
    saving.value = false
  }
}
</script>
