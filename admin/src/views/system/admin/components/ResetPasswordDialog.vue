<template>
    <el-dialog
        v-model="visible"
        :title="$t('admin.resetPasswordTitle')"
        width="400px"
        :close-on-click-modal="false"
        @close="handleClose"
    >
        <div class="reset-info">
            <p>
                {{ $t('admin.adminLabel') }}<strong>{{ adminInfo?.username }}</strong>
            </p>
            <p>
                {{ $t('admin.nicknameLabel') }}<strong>{{ adminInfo?.nickname }}</strong>
            </p>
        </div>

        <el-form ref="formRef" :model="form" :rules="rules" label-width="80px">
            <el-form-item :label="$t('admin.newPassword')" prop="password">
                <el-input
                    v-model="form.password"
                    type="password"
                    :placeholder="$t('admin.newPasswordPlaceholder')"
                    show-password
                />
            </el-form-item>

            <el-form-item :label="$t('admin.confirmPassword')" prop="confirmPassword">
                <el-input
                    v-model="form.confirmPassword"
                    type="password"
                    :placeholder="$t('admin.confirmNewPasswordPlaceholder')"
                    show-password
                />
            </el-form-item>
        </el-form>

        <template #footer>
            <span class="dialog-footer">
                <el-button @click="handleClose">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="submitLoading" @click="handleSubmit">
                    {{ $t('admin.resetConfirm') }}
                </el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script setup lang="ts" name="ResetPasswordDialog">
import { ElForm, ElMessage } from 'element-plus'
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { adminApi } from '@/api/admin'
import type { AdminInfo } from '@/types/api'

const { t } = useI18n()

interface Props {
    modelValue: boolean
    adminInfo: AdminInfo | null
}

interface Emits {
    (e: 'update:modelValue', value: boolean): void
    (e: 'success'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

// 表单引用
const formRef = ref<InstanceType<typeof ElForm>>()

// 弹窗显示状态
const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
})

// 表单数据
const form = reactive({
    password: '',
    confirmPassword: ''
})

// 表单验证规则
const rules = computed(() => ({
    password: [
        { required: true, message: t('admin.validate.passwordRequired'), trigger: 'blur' },
        { min: 6, max: 20, message: t('admin.validate.passwordLength'), trigger: 'blur' }
    ],
    confirmPassword: [
        { required: true, message: t('admin.validate.confirmRequired'), trigger: 'blur' },
        {
            validator: (rule: any, value: string, callback: (error?: Error) => void) => {
                if (value !== form.password) {
                    callback(new Error(t('admin.validate.passwordMismatch')))
                } else {
                    callback()
                }
            },
            trigger: 'blur'
        }
    ]
}))

// 提交加载状态
const submitLoading = ref(false)

// 提交表单
const handleSubmit = async () => {
    if (!formRef.value || !props.adminInfo) return

    try {
        await formRef.value.validate()

        submitLoading.value = true

        await adminApi.resetAdminPassword(props.adminInfo.id, {
            password: form.password
        })

        ElMessage.success(t('admin.resetSuccess'))
        emit('success')
        handleClose()
    } catch (error) {
        console.error('Reset password failed:', error)
    } finally {
        submitLoading.value = false
    }
}

// 关闭弹窗
const handleClose = () => {
    formRef.value?.resetFields()
    Object.assign(form, {
        password: '',
        confirmPassword: ''
    })
    visible.value = false
}
</script>

<style lang="scss" scoped>
.reset-info {
    background-color: var(--el-fill-color-light);
    border-radius: 6px;
    padding: 16px;
    margin-bottom: 20px;

    p {
        margin: 0 0 8px 0;
        color: var(--el-text-color-regular);

        &:last-child {
            margin-bottom: 0;
        }

        strong {
            color: var(--el-text-color-primary);
        }
    }
}

.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
</style>
