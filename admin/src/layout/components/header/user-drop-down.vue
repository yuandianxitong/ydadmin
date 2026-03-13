<template>
    <el-dropdown class="px-2" @command="handleCommand">
        <div class="flex items-center cursor-pointer">
            <el-avatar :size="34" :src="appStore.getImageUrl(userInfo.avatar)">
                <Icon name="i-svg:user" :size="18" />
            </el-avatar>
            <div class="ml-3 mr-1">{{ userInfo.username }}</div>
            <div class="i-svg:chevron-down"></div>
        </div>

        <template #dropdown>
            <el-dropdown-menu>
                <el-dropdown-item command="cache">{{ $t('navbar.clearCache') }}</el-dropdown-item>
                <el-dropdown-item command="logout">{{ $t('navbar.logout') }}</el-dropdown-item>
            </el-dropdown-menu>
        </template>
    </el-dropdown>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

import useAppStore from '@/store/modules/app.store'
import useUserStore from '@/store/modules/user.store'
import feedback from '@/utils/feedback'

const { t } = useI18n()
const userStore = useUserStore()
const appStore = useAppStore()
const userInfo = computed(() => userStore.userInfo)

const handleCommand = async (command: string) => {
    switch (command) {
        case 'logout':
            await feedback.confirm(t('navbar.logoutConfirm'))
            userStore.logout()
            break
        case 'cache':
            // 清理本地缓存
            await feedback.confirm(t('navbar.clearCacheConfirm'))
            localStorage.clear()
            sessionStorage.clear()
            window.location.reload()
            break
    }
}
</script>
