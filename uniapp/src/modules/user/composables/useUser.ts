import { ref } from 'vue'
import { userApi } from '@/api/user'
import { useUserStore } from '@/store/user.store'
import type { UserInfo } from '@/types/api'

export function useUser() {
  const userStore = useUserStore()
  const loading = ref(false)

  async function loadProfile(): Promise<UserInfo> {
    loading.value = true
    try {
      return await userStore.getUserInfo()
    } finally {
      loading.value = false
    }
  }

  async function updateProfile(data: Partial<UserInfo>) {
    loading.value = true
    try {
      await userApi.updateProfile(data)
      await userStore.getUserInfo()
      uni.showToast({ title: '保存成功' })
    } finally {
      loading.value = false
    }
  }

  async function changePassword(data: { old_password: string; new_password: string }) {
    loading.value = true
    try {
      await userApi.changePassword(data)
      uni.showToast({ title: '修改成功' })
      setTimeout(() => userStore.logout(), 1500)
    } finally {
      loading.value = false
    }
  }

  return { loading, loadProfile, updateProfile, changePassword }
}
