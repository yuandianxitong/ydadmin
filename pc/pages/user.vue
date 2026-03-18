<template>
  <div class="mx-auto max-w-1200px px-6 py-8">
    <div class="flex gap-6">
      <!-- Sidebar -->
      <aside class="w-60 flex-shrink-0">
        <!-- User info card -->
        <div class="card p-5 mb-4">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 rounded-full bg-[var(--color-primary)] text-white flex items-center justify-center text-lg font-bold flex-shrink-0">
              {{ userInitial }}
            </div>
            <div class="min-w-0">
              <div class="font-semibold text-gray-900 truncate">{{ userInfo?.nickname || '用户' }}</div>
              <div class="text-xs text-gray-400 mt-0.5">{{ userInfo?.mobile || '' }}</div>
            </div>
          </div>
          <!-- Balance & Points stats -->
          <div class="flex border-t border-gray-100 pt-3">
            <NuxtLink to="/user/balance" class="flex-1 text-center hover:opacity-80 transition-opacity">
              <div class="text-lg font-bold text-amber-600">{{ balance }}</div>
              <div class="text-xs text-gray-400 mt-0.5">余额</div>
            </NuxtLink>
            <div class="w-px bg-gray-100" />
            <NuxtLink to="/user/points" class="flex-1 text-center hover:opacity-80 transition-opacity">
              <div class="text-lg font-bold text-indigo-600">{{ points }}</div>
              <div class="text-xs text-gray-400 mt-0.5">积分</div>
            </NuxtLink>
          </div>
        </div>

        <!-- Navigation menu -->
        <div class="card py-2">
          <template v-for="item in menuItems" :key="item.path">
            <NuxtLink
              v-if="!item.disabled"
              :to="item.path"
              class="flex items-center px-5 py-3 text-sm transition-colors"
              :class="isActive(item.path) ? 'text-[var(--color-primary)] bg-[var(--color-primary)]/5 font-medium' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'"
            >
              {{ item.label }}
            </NuxtLink>
            <div
              v-else
              class="flex items-center px-5 py-3 text-sm text-gray-300 cursor-not-allowed"
            >
              {{ item.label }}
              <span class="ml-auto text-xs">即将开放</span>
            </div>
          </template>
        </div>
      </aside>

      <!-- Main content -->
      <div class="flex-1 min-w-0">
        <NuxtPage />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { userApi } from '~/api/user'

definePageMeta({ middleware: 'auth' })

const route = useRoute()

const userInfo = ref<{ nickname: string; mobile: string; avatar: string } | null>(null)
const balance = ref('0.00')
const points = ref(0)

const menuItems = [
  { label: '个人资料', path: '/user/profile', disabled: false },
  { label: '我的余额', path: '/user/balance', disabled: false },
  { label: '我的积分', path: '/user/points', disabled: false },
  { label: '修改密码', path: '/user/password', disabled: false },
  { label: '我的收藏', path: '/user/favorites', disabled: true },
  { label: '消息通知', path: '/user/notifications', disabled: true },
  { label: '意见反馈', path: '/user/feedback', disabled: true },
  { label: '账号安全', path: '/user/security', disabled: true },
]

const userInitial = computed(() => {
  const name = userInfo.value?.nickname || '用'
  return name.charAt(0).toUpperCase()
})

function isActive(path: string) {
  return route.path === path
}

async function refreshUserInfo() {
  try {
    const [profileRes, balanceRes, pointsRes] = await Promise.all([
      userApi.getProfile(),
      userApi.getBalance(),
      userApi.getPoints(),
    ])
    if (profileRes.code === 200) {
      userInfo.value = profileRes.data
    }
    if (balanceRes.code === 200) {
      balance.value = balanceRes.data.balance
    }
    if (pointsRes.code === 200) {
      points.value = pointsRes.data.points
    }
  } catch {
    // ignore errors silently
  }
}

provide('refreshUserInfo', refreshUserInfo)

onMounted(() => {
  refreshUserInfo()
})
</script>
