<template>
  <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
    <div class="mx-auto max-w-1200px px-4 h-14 flex items-center justify-between">
      <!-- Logo + Nav -->
      <div class="flex items-center gap-8">
        <NuxtLink to="/" class="flex items-center gap-2">
          <span class="text-xl font-bold text-[var(--color-primary)]">元点Admin</span>
        </NuxtLink>
        <nav class="flex items-center gap-1">
          <NuxtLink
            v-for="item in navItems"
            :key="item.path"
            :to="item.path"
            class="px-3 py-1.5 text-sm rounded transition-colors"
            :class="isActive(item.path) ? 'text-[var(--color-primary)] font-medium' : 'text-gray-600 hover:text-[var(--color-primary)]'"
          >
            {{ item.label }}
          </NuxtLink>
        </nav>
      </div>

      <!-- Right -->
      <div class="flex items-center gap-4">
        <template v-if="userStore.isLoggedIn">
          <NuxtLink to="/user" class="text-sm text-gray-600 hover:text-[var(--color-primary)]">个人中心</NuxtLink>
          <button class="text-sm text-gray-400 hover:text-red-500" @click="handleLogout">退出</button>
        </template>
        <template v-else>
          <NuxtLink to="/login" class="text-sm text-gray-600 hover:text-[var(--color-primary)]">登录</NuxtLink>
          <NuxtLink to="/register" class="text-sm px-4 py-1 bg-[var(--color-primary)] text-white rounded hover:opacity-90 transition-opacity">注册</NuxtLink>
        </template>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { useUserStore } from '~/store/user'

const userStore = useUserStore()
const router = useRouter()
const route = useRoute()

const navItems = [
  { label: '首页', path: '/' },
  { label: '文章', path: '/article' },
]

function isActive(path: string) {
  if (path === '/') return route.path === '/' || route.path === ''
  return route.path.startsWith(path)
}

async function handleLogout() {
  await userStore.logout()
  router.push('/')
}
</script>
