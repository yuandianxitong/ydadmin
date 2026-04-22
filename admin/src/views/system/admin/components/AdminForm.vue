<template>
    <el-dialog
        v-model="visible"
        :title="isEdit ? $t('admin.editAdmin') : $t('admin.addAdmin')"
        width="680px"
        :close-on-click-modal="false"
        @closed="resetForm"
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
                        <el-input
                            v-model="form.nickname"
                            :placeholder="$t('admin.nicknamePlaceholder')"
                        />
                    </el-form-item>
                </el-col>
            </el-row>

            <el-row :gutter="16">
                <el-col :span="12">
                    <el-form-item :label="$t('admin.email')" prop="email">
                        <el-input
                            v-model="form.email"
                            :placeholder="$t('admin.emailPlaceholder')"
                        />
                    </el-form-item>
                </el-col>

                <el-col :span="12">
                    <el-form-item :label="$t('admin.mobile')" prop="mobile">
                        <el-input
                            v-model="form.mobile"
                            :placeholder="$t('admin.mobilePlaceholder')"
                        />
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
                    <el-form-item :label="$t('admin.department')" prop="department_id">
                        <el-tree-select
                            v-model="form.department_id"
                            :data="departmentOptions"
                            node-key="id"
                            :props="{ label: 'name' }"
                            :placeholder="$t('admin.deptInputPlaceholder')"
                            check-strictly
                            clearable
                            style="width: 100%"
                        />
                    </el-form-item>
                </el-col>

                <el-col :span="12">
                    <el-form-item :label="$t('admin.position')" prop="position">
                        <el-input
                            v-model="form.position"
                            :placeholder="$t('admin.positionPlaceholder')"
                        />
                    </el-form-item>
                </el-col>
            </el-row>

            <el-form-item :label="$t('admin.avatar')" prop="avatar">
                <div>
                    <el-upload
                        class="avatar-uploader"
                        :show-file-list="false"
                        :on-success="handleAvatarSuccess"
                        :before-upload="beforeAvatarUpload"
                        action="/adminapi/upload/image"
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
                </div>
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
                <el-button type="primary" :loading="submitting" @click="handleSubmit">
                    {{ $t('common.confirm') }}
                </el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script setup lang="ts" name="AdminForm">
import { Plus } from '@element-plus/icons-vue'
import type { FormRules, UploadProps } from 'element-plus'
import { ElMessage } from 'element-plus'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import { adminApi } from '@/api/admin'
import { useFormDialog } from '@/hooks/useFormDialog'
import { useUserStore } from '@/store'
import useAppStore from '@/store/modules/app.store'
import type { AdminInfo, AdminReq } from '@/types/system'

interface Props {
    modelValue: boolean
    formData: Partial<AdminInfo>
    roleOptions: Array<{ id: number; name: string; title: string }>
    departmentOptions: Array<{ id: number; name: string; children?: any[] }>
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

// 表单内部完整字段（包含 confirmPassword 仅做前端校验）
type AdminFormData = AdminReq & {
    id?: number
    confirmPassword?: string
}

// 是否编辑模式
const isEdit = computed(() => !!props.formData.id)

// 把外部 formData 中的 roles[] 转成 role_ids[]，并清空密码字段
const sourceData = computed<Partial<AdminFormData> | undefined>(() => {
    const data = props.formData
    if (!data || !data.id) return undefined
    return {
        id: data.id,
        username: data.username || '',
        email: data.email || '',
        mobile: data.mobile || '',
        password: '',
        confirmPassword: '',
        nickname: data.nickname || '',
        avatar: data.avatar || '',
        department_id: data.department_id || null,
        position: data.position || '',
        status: data.status ?? 1,
        role_ids: data.roles?.map((role) => role.id) || []
    }
})

const { form, formRef, submitting, visible, handleSubmit, handleClose, resetForm } =
    useFormDialog<AdminFormData>({
        defaultForm: {
            id: undefined,
            username: '',
            email: '',
            mobile: '',
            password: '',
            confirmPassword: '',
            nickname: '',
            avatar: '',
            department_id: null,
            position: '',
            status: 1,
            role_ids: []
        },
        modelValue: () => props.modelValue,
        onUpdate: (v) => emit('update:modelValue', v),
        onSuccess: () => emit('success'),
        // 创建时携带 password，去除 confirmPassword
        createFn: (data) => {
            const { confirmPassword: _c, id: _id, ...payload } = data
            return adminApi.createAdmin(payload)
        },
        // 编辑时去除 password / confirmPassword（重置密码走单独接口）
        updateFn: (id, data) => {
            const { confirmPassword: _c, password: _p, id: _id, ...payload } = data
            return adminApi.updateAdmin(id, payload)
        },
        sourceData: () => sourceData.value
    })

// 上传请求头
const uploadHeaders = computed(() => ({
    Authorization: `Bearer ${userStore.getToken()}`
}))

// 表单验证规则
const rules = computed<FormRules>(() => ({
    username: [
        { required: true, message: t('admin.validate.usernameRequired'), trigger: 'blur' },
        { min: 3, max: 20, message: t('admin.validate.usernameLength'), trigger: 'blur' },
        { pattern: /^[a-zA-Z0-9_]+$/, message: t('admin.validate.usernameFormat'), trigger: 'blur' }
    ],
    email: [
        { required: true, message: t('admin.validate.emailRequired'), trigger: 'blur' },
        { type: 'email' as const, message: t('admin.validate.emailFormat'), trigger: 'blur' }
    ],
    mobile: [
        { pattern: /^1[3-9]\d{9}$/, message: t('admin.validate.mobileFormat'), trigger: 'blur' }
    ],
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
