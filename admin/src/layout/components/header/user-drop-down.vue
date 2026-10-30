<template>
    <el-dropdown @command="handleCommand">
        <div class="user-trigger">
            <div class="user-avatar">
                <template v-if="userInfo?.avatar">
                    <img :src="appStore.getImageUrl(userInfo.avatar)" alt="avatar" />
                </template>
                <template v-else>
                    {{ initials }}
                </template>
            </div>
            <span class="user-name">{{ userInfo?.username }}</span>
            <div class="i-svg:chevron-down user-chev"></div>
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
    try {
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
    } catch {
        // 用户取消确认，忽略
    }
}
const initials = computed(() => (userInfo.value?.username || '').slice(0, 2).toUpperCase())
</script>

<style lang="scss" scoped>
.user-trigger {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 10px 4px 4px;
    border-radius: 20px;
    cursor: pointer;
    transition: background 0.15s;

    &:hover {
        background: var(--ink-100);
    }
}

.user-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--brand-500);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 11px;
    font-weight: 600;
    flex-shrink: 0;
    overflow: hidden;

    img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
}

.user-name {
    font-size: 13px;
    color: var(--ink-700);
    white-space: nowrap;
}

.user-chev {
    width: 12px;
    height: 12px;
    color: var(--ink-400);
    transform: rotate(0deg);
}
</style>
