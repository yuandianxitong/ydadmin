<template>
  <div>
    <h2 class="text-xl font-bold text-gray-900 mb-6">修改密码</h2>

    <div class="card p-6">
      <form @submit.prevent="handleChangePassword">
        <div class="max-w-400px flex flex-col gap-4">
          <div>
            <label class="block text-sm text-gray-600 mb-1">当前密码</label>
            <input
              v-model="passwordForm.old_password"
              type="password"
              placeholder="请输入当前密码"
              class="form-input"
            />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">新密码</label>
            <input
              v-model="passwordForm.new_password"
              type="password"
              placeholder="请输入新密码"
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
  </div>
</template>

<script setup lang="ts">
import { useMessage } from 'naive-ui'
import { userApi } from '~/api/user'

const message = useMessage()
const changingPwd = ref(false)

const passwordForm = reactive({ old_password: '', new_password: '' })

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
