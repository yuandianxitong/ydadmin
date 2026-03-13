<template>
    <el-popover placement="bottom" :width="360" trigger="click" @show="loadNotifications">
        <template #reference>
            <el-badge :value="unreadCount" :hidden="unreadCount === 0" :max="99" class="mr-4">
                <div
                    class="bg-[#f5f5f9] text-[#384a65] w-[34px] h-[34px] rounded-full flex items-center justify-center cursor-pointer"
                >
                    <el-tooltip effect="dark" :content="$t('notification.title')" placement="bottom">
                        <Icon name="i-svg:bell" />
                    </el-tooltip>
                </div>
            </el-badge>
        </template>

        <div class="notification-popover">
            <div class="popover-header">
                <span class="title">{{ $t('notification.messages') }}</span>
                <el-button
                    v-if="unreadCount > 0"
                    type="primary"
                    link
                    size="small"
                    @click="handleReadAll"
                >
                    {{ $t('notification.markAllRead') }}
                </el-button>
            </div>

            <el-scrollbar max-height="360px">
                <div v-if="notifications.length === 0" class="empty-text">{{ $t('notification.noNotification') }}</div>
                <div
                    v-for="item in notifications"
                    :key="item.id"
                    class="notification-item"
                    :class="{ unread: !item.is_read }"
                    @click="handleRead(item)"
                >
                    <div class="item-dot" :class="{ active: !item.is_read }"></div>
                    <div class="item-content">
                        <div class="item-title">{{ item.title }}</div>
                        <div class="item-time">{{ formatTime(item.created_at) }}</div>
                    </div>
                </div>
            </el-scrollbar>

            <div class="popover-footer">
                <router-link to="/system/notification" class="view-all">{{ $t('notification.viewAll') }}</router-link>
            </div>
        </div>
    </el-popover>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { notificationApi } from '@/api/notification'

const { t } = useI18n()
const unreadCount = ref(0)
const notifications = ref<any[]>([])

const formatTime = (time: string) => {
    if (!time) return ''
    const d = new Date(time)
    const now = new Date()
    const diff = now.getTime() - d.getTime()
    if (diff < 60000) return t('notification.justNow')
    if (diff < 3600000) return Math.floor(diff / 60000) + t('notification.minutesAgo')
    if (diff < 86400000) return Math.floor(diff / 3600000) + t('notification.hoursAgo')
    return d.toLocaleDateString('zh-CN')
}

const fetchUnreadCount = async () => {
    try {
        const res = await notificationApi.getUnreadCount()
        unreadCount.value = res.data?.count || 0
    } catch {
        // silent
    }
}

const loadNotifications = async () => {
    try {
        const res = await notificationApi.getMine({ page: 1, limit: 10 })
        notifications.value = res.data?.list || []
    } catch {
        // silent
    }
}

const handleRead = async (item: any) => {
    if (!item.is_read) {
        try {
            await notificationApi.markAsRead(item.id)
            item.is_read = true
            unreadCount.value = Math.max(0, unreadCount.value - 1)
        } catch {
            // silent
        }
    }
}

const handleReadAll = async () => {
    try {
        await notificationApi.markAllAsRead()
        unreadCount.value = 0
        notifications.value.forEach((n) => (n.is_read = true))
    } catch {
        // silent
    }
}

onMounted(() => {
    fetchUnreadCount()
    // 每分钟刷新未读数
    setInterval(fetchUnreadCount, 60000)
})
</script>

<style lang="scss" scoped>
.notification-popover {
    margin: -12px;

    .popover-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        border-bottom: 1px solid var(--el-border-color-lighter);

        .title {
            font-weight: 600;
            font-size: 14px;
            color: var(--el-text-color-primary);
        }
    }

    .empty-text {
        text-align: center;
        padding: 40px 0;
        color: var(--el-text-color-placeholder);
        font-size: 14px;
    }

    .notification-item {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 12px 16px;
        cursor: pointer;
        transition: background-color 0.2s;

        &:hover {
            background-color: var(--el-color-primary-light-9);
        }

        &.unread {
            background-color: #f0f5ff;
        }

        .item-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-top: 6px;
            flex-shrink: 0;
            background-color: var(--el-border-color-lighter);

            &.active {
                background-color: var(--el-color-primary);
            }
        }

        .item-content {
            flex: 1;
            min-width: 0;

            .item-title {
                font-size: 13px;
                color: var(--el-text-color-primary);
                line-height: 1.5;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .item-time {
                font-size: 12px;
                color: var(--el-text-color-placeholder);
                margin-top: 4px;
            }
        }
    }

    .popover-footer {
        text-align: center;
        padding: 10px;
        border-top: 1px solid var(--el-border-color-lighter);

        .view-all {
            font-size: 13px;
            color: var(--el-color-primary);
            text-decoration: none;

            &:hover {
                text-decoration: underline;
            }
        }
    }
}
</style>
