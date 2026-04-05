<template>
    <el-dialog
        v-model="visible"
        :title="t('userMgmt.detail.title')"
        width="600px"
        :close-on-click-modal="false"
        @close="handleClose"
    >
        <el-descriptions :column="2" border>
            <el-descriptions-item :label="t('userMgmt.avatar')" :span="2">
                <el-image
                    v-if="userData?.avatar"
                    :src="appStore.getImageUrl(userData.avatar)"
                    style="width: 60px; height: 60px; border-radius: 50%"
                    fit="cover"
                >
                    <template #error>
                        <div class="avatar-fallback">
                            {{ (userData?.nickname || '用')[0] }}
                        </div>
                    </template>
                </el-image>
                <span v-else class="text-gray-400">{{ t('userMgmt.detail.noAvatar') }}</span>
            </el-descriptions-item>
            <el-descriptions-item :label="t('userMgmt.nickname')">
                {{ userData?.nickname || '-' }}
            </el-descriptions-item>
            <el-descriptions-item :label="t('userMgmt.mobile')">
                {{ userData?.mobile || '-' }}
            </el-descriptions-item>
            <el-descriptions-item :label="t('userMgmt.balance')">
                <span class="balance-text">¥{{ userData?.balance || '0.00' }}</span>
            </el-descriptions-item>
            <el-descriptions-item :label="t('userMgmt.points')">
                <span class="points-text">{{ userData?.points || 0 }}</span>
            </el-descriptions-item>
            <el-descriptions-item :label="t('userMgmt.status')">
                <el-tag :type="userData?.status === 1 ? 'success' : 'danger'" size="small">
                    {{ userData?.status === 1 ? t('userMgmt.statusEnabled') : t('userMgmt.statusDisabled') }}
                </el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="t('userMgmt.detail.loginCount')">
                {{ userData?.login_count || 0 }}
            </el-descriptions-item>
            <el-descriptions-item :label="t('userMgmt.detail.lastLoginIp')">
                {{ userData?.last_login_ip || '-' }}
            </el-descriptions-item>
            <el-descriptions-item :label="t('userMgmt.detail.lastLoginTime')">
                {{ userData?.last_login_time || '-' }}
            </el-descriptions-item>
            <el-descriptions-item :label="t('userMgmt.detail.createdAt')" :span="2">
                {{ userData?.created_at || '-' }}
            </el-descriptions-item>
        </el-descriptions>

        <template #footer>
            <span class="dialog-footer">
                <el-button @click="handleClose">{{ t('userMgmt.detail.close') }}</el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script setup lang="ts" name="UserDetailDialog">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import type { UserItem } from '@/api/user'
import useAppStore from '@/store/modules/app.store'

const { t } = useI18n()

interface Props {
    modelValue: boolean
    userData: UserItem | null
}

interface Emits {
    (e: 'update:modelValue', value: boolean): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const appStore = useAppStore()

// 弹窗显示状态
const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
})

// 关闭弹窗
const handleClose = () => {
    visible.value = false
}
</script>

<style lang="scss" scoped>
.avatar-fallback {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background-color: var(--el-color-primary-light-7);
    color: var(--el-color-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 600;
}

.balance-text {
    color: var(--el-color-warning);
    font-weight: 600;
}

.points-text {
    color: var(--el-color-primary);
    font-weight: 600;
}

.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
</style>
