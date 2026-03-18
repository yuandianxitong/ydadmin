<template>
    <el-dialog
        v-model="visible"
        :title="isEdit ? $t('admin.editAdmin') : $t('admin.addAdmin')"
        width="680px"
        :close-on-click-modal="false"
        @close="handleClose"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
            <el-row :gutter="16">
                <el-col :span="12">
                    <el-form-item :label="$t('admin.username')" prop="username">
                        <el-input
                            v-model="form.username"
                            :placeholder="$t('admin.usernamePlaceholder')"
                            :disabled="isEdit"
                        />
                    </el-form-item>
                </el-col>

                <el-col :span="12">
                    <el-form-item :label="$t('admin.nickname')" prop="nickname">
                        <el-input v-model="form.nickname" :placeholder="$t('admin.nicknamePlaceholder')" />
                    </el-form-item>
                </el-col>
            </el-row>

            <el-row :gutter="16">
                <el-col :span="12">
                    <el-form-item :label="$t('admin.email')" prop="email">
                        <el-input v-model="form.email" :placeholder="$t('admin.emailPlaceholder')" />
                    </el-form-item>
                </el-col>

                <el-col :span="12">
                    <el-form-item :label="$t('admin.mobile')" prop="mobile">
                        <el-input v-model="form.mobile" :placeholder="$t('admin.mobilePlaceholder')" />
                    </el-form-item>
                </el-col>
            </el-row>

            <el-row v-if="!isEdit" :gutter="16">
                <el-col :span="12">
                    <el-form-item :label="$t('admin.password')" prop="password">
                        <el-input
                            v-model="form.password"
                            type="password"
                            :placeholder="$t('admin.passwordPlaceholder')"
                            show-password
                        />
                    </el-form-item>
                </el-col>

                <el-col :span="12">
                    <el-form-item :label="$t('admin.confirmPassword')" prop="confirmPassword">
                        <el-input
                            v-model="form.confirmPassword"
                            type="password"
                            :placeholder="$t('admin.confirmPasswordPlaceholder')"
                            show-password
                        />
                    </el-form-item>
                </el-col>
            </el-row>

            <el-row :gutter="16">
                <el-col :span="12">
                    <el-form-item :label="$t('admin.department')" prop="department">
                        <el-input v-model="form.department" :placeholder="$t('admin.deptInputPlaceholder')" />
                    </el-form-item>
                </el-col>

                <el-col :span="12">
                    <el-form-item :label="$t('admin.position')" prop="position">
                        <el-input v-model="form.position" :placeholder="$t('admin.positionPlaceholder')" />
                    </el-form-item>
                </el-col>
            </el-row>

            <el-form-item :label="$t('admin.avatar')" prop="avatar">
                <el-upload
                    class="avatar-uploader"
                    :show-file-list="false"
                    :on-success="handleAvatarSuccess"
                    :before-upload="beforeAvatarUpload"
                    action="/api/upload/image"
                    :headers="uploadHeaders"
                >
                    <img
                        v-if="form.avatar"
                        :src="appStore.getImageUrl(form.avatar)"
                        class="avatar"
                    />
                    <el-icon v-else class="avatar-uploader-icon"><Plus /></el-icon>
                </el-upload>
                <div class="upload-tip">{{ $t('admin.avatarTip') }}</div>
            </el-form-item>

            <el-form-item :label="$t('admin.role')" prop="role_ids">
                <el-select
                    v-model="form.role_ids"
                    multiple
                    :placeholder="$t('admin.rolePlaceholder')"
                    style="width: 100%"
                >
                    <el-option
                        v-for="role in roleOptions"
                        :key="role.id"
                        :label="role.title"
                        :value="role.id"
                    />
                </el-select>
            </el-form-item>

            <el-form-item :label="$t('common.status')" prop="status">
                <el-radio-group v-model="form.status">
                    <el-radio :value="1">{{ $t('common.enable') }}</el-radio>
                    <el-radio :value="0">{{ $t('common.disable') }}</el-radio>
                </el-radio-group>
            </el-form-item>
        </el-form>

        <template #footer>
            <span class="dialog-footer">
                <el-button @click="handleClose">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="submitLoading" @click="handleSubmit">
                    {{ $t('common.confirm') }}
                </el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script setup lang="ts" name="AdminForm">
import { Plus } from '@element-plus/icons-vue'
import type { UploadProps } from 'element-plus'
import { ElForm, ElMessage } from 'element-plus'
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { adminApi } from '@/api/admin'
import { useUserStore } from '@/store'
import useAppStore from '@/store/modules/app.store'
import type { AdminInfo, AdminReq } from '@/types/api'

interface Props {
    modelValue: boolean
    formData: Partial<AdminInfo>
    roleOptions: Array<{ id: number; name: string; title: string }>
}

interface Emits {
    (e: 'update:modelValue', value: boolean): void
    (e: 'success'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { t } = useI18n()
const userStore = useUserStore()
const appStore = useAppStore()

// 表单引用
const formRef = ref<InstanceType<typeof ElForm>>()

// 弹窗显示状态
const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
})

// 是否编辑模式
const isEdit = computed(() => !!props.formData.id)

// 表单数据
const form = reactive<AdminReq & { confirmPassword?: string }>({
    username: '',
    email: '',
    mobile: '',
    password: '',
    confirmPassword: '',
    nickname: '',
    avatar: '',
    department: '',
    position: '',
    status: 1,
    role_ids: []
})

// 上传请求头
const uploadHeaders = computed(() => ({
    Authorization: `Bearer ${userStore.getToken()}`
}))

// 表单验证规则
const rules = computed(() => ({
    username: [
        { required: true, message: t('admin.validate.usernameRequired'), trigger: 'blur' },
        { min: 3, max: 20, message: t('admin.validate.usernameLength'), trigger: 'blur' },
        { pattern: /^[a-zA-Z0-9_]+$/, message: t('admin.validate.usernameFormat'), trigger: 'blur' }
    ],
    email: [
        { required: true, message: t('admin.validate.emailRequired'), trigger: 'blur' },
        { type: 'email' as const, message: t('admin.validate.emailFormat'), trigger: 'blur' }
    ],
    mobile: [{ pattern: /^1[3-9]\d{9}$/, message: t('admin.validate.mobileFormat'), trigger: 'blur' }],
    password: isEdit.value
        ? []
        : [
              { required: true, message: t('admin.validate.passwordRequired'), trigger: 'blur' },
              { min: 6, max: 20, message: t('admin.validate.passwordLength'), trigger: 'blur' }
          ],
    confirmPassword: isEdit.value
        ? []
        : [
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
          ],
    nickname: [
        { required: true, message: t('admin.validate.nicknameRequired'), trigger: 'blur' },
        { max: 20, message: t('admin.validate.nicknameLength'), trigger: 'blur' }
    ]
}))

// 提交加载状态
const submitLoading = ref(false)

// 监听表单数据变化
watch(
    () => props.formData,
    (newData) => {
        if (newData) {
            Object.assign(form, {
                username: newData.username || '',
                email: newData.email || '',
                mobile: newData.mobile || '',
                password: '',
                confirmPassword: '',
                nickname: newData.nickname || '',
                avatar: newData.avatar || '',
                department: newData.department || '',
                position: newData.position || '',
                status: newData.status || 1,
                role_ids: newData.roles?.map((role) => role.id) || []
            })
        }
    },
    { deep: true, immediate: true }
)

// 头像上传成功
const handleAvatarSuccess: UploadProps['onSuccess'] = (response) => {
    if (response.code === 200) {
        form.avatar = response.data.url
        ElMessage.success(t('admin.avatarUploadSuccess'))
    } else {
        ElMessage.error(response.message || t('admin.avatarUploadFailed'))
    }
}

// 头像上传前检查
const beforeAvatarUpload: UploadProps['beforeUpload'] = (rawFile) => {
    const isJPGOrPNG = rawFile.type === 'image/jpeg' || rawFile.type === 'image/png'
    const isLt2M = rawFile.size / 1024 / 1024 < 2

    if (!isJPGOrPNG) {
        ElMessage.error(t('admin.avatarFormatError'))
        return false
    }
    if (!isLt2M) {
        ElMessage.error(t('admin.avatarSizeError'))
        return false
    }
    return true
}

// 提交表单
const handleSubmit = async () => {
    if (!formRef.value) return

    try {
        await formRef.value.validate()

        submitLoading.value = true

        // 准备提交数据
        const submitData: AdminReq = {
            username: form.username,
            email: form.email,
            mobile: form.mobile,
            nickname: form.nickname,
            avatar: form.avatar,
            department: form.department,
            position: form.position,
            status: form.status,
            role_ids: form.role_ids
        }

        if (!isEdit.value) {
            submitData.password = form.password
        }

        if (isEdit.value && props.formData.id) {
            // 编辑
            await adminApi.updateAdmin(props.formData.id, submitData)
            ElMessage.success(t('message.updateSuccess'))
        } else {
            // 新增
            await adminApi.createAdmin(submitData)
            ElMessage.success(t('message.createSuccess'))
        }

        emit('success')
        handleClose()
    } catch (error) {
        console.error('提交失败:', error)
    } finally {
        submitLoading.value = false
    }
}

// 关闭弹窗
const handleClose = () => {
    formRef.value?.resetFields()
    visible.value = false
}
</script>

<style lang="scss" scoped>
.avatar-uploader {
    .avatar {
        width: 80px;
        height: 80px;
        border-radius: 6px;
        display: block;
        object-fit: cover;
    }
}

.avatar-uploader :deep(.el-upload) {
    border: 1px dashed var(--el-border-color);
    border-radius: 6px;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition: var(--el-transition-duration-fast);
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;

    &:hover {
        border-color: var(--el-color-primary);
    }
}

.avatar-uploader-icon {
    font-size: 28px;
    color: var(--color-text-disabled);
    text-align: center;
}

.upload-tip {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-top: 8px;
}

.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
</style>
