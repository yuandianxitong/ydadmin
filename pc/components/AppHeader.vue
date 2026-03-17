<template>
  <header class="bg-white border-b border-gray-200">
    <div class="mx-auto max-w-1200px px-6 h-16 flex items-center justify-between">
      <NuxtLink to="/" class="text-xl font-bold text-blue-600">元点Admin</NuxtLink>

      <nav class="flex items-center gap-8">
        <NuxtLink to="/" class="text-gray-600 hover:text-blue-600 transition-colors">首页</NuxtLink>
        <NuxtLink to="/article" class="text-gray-600 hover:text-blue-600 transition-colors">文章</NuxtLink>
        <template v-if="userStore.isLoggedIn">
          <NuxtLink to="/user" class="text-gray-600 hover:text-blue-600 transition-colors">个人中心</NuxtLink>
          <button class="text-gray-400 hover:text-red-500 text-sm transition-colors" @click="handleLogout">退出</button>
        </template>
        <template v-else>
          <NuxtLink to="/login" class="btn-primary text-sm !py-1.5 !px-4">登录</NuxtLink>
        </template>
      </nav>
    </div>
  </header>
</template>

<script setup lang="ts">
import { useUserStore } from '~/store/user'

const userStore = useUserStore()
const router = useRouter()

async function handleLogout() {
  await userStore.logout()
  router.push('/')
}
</script>
