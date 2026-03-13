<template>
    <el-dialog
        v-model="visible"
        :title="isEdit ? $t('role.editRole') : $t('role.addRole')"
        width="600px"
        :close-on-click-modal="false"
        @close="handleClose"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
            <el-form-item :label="$t('role.roleCode')" prop="name">
                <el-input v-model="form.name" :placeholder="$t('role.codePlaceholder')" :disabled="isEdit" />
                <div class="form-tip">{{ $t('role.codeTip') }}</div>
            </el-form-item>

            <el-form-item :label="$t('role.roleName')" prop="title">
                <el-input v-model="form.title" :placeholder="$t('role.namePlaceholder')" />
            </el-form-item>

            <el-form-item :label="$t('role.description')" prop="description">
                <el-input
                    v-model="form.description"
                    type="textarea"
                    :rows="3"
                    :placeholder="$t('role.descPlaceholder')"
                />
            </el-form-item>

            <el-form-item :label="$t('role.dataScope')" prop="data_scope">
                <el-select v-model="form.data_scope" :placeholder="$t('common.selectPlaceholder')">
                    <el-option :label="$t('role.dataScopeOptions.all')" :value="1" />
                    <el-option :label="$t('role.dataScopeOptions.department')" :value="2" />
                    <el-option :label="$t('role.dataScopeOptions.departmentAndBelow')" :value="3" />
                    <el-option :label="$t('role.dataScopeOptions.self')" :value="4" />
                    <el-option :label="$t('role.dataScopeOptions.custom')" :value="5" />
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

<script setup lang="ts" name="RoleForm">
import { ElForm, ElMessage } from 'element-plus'
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { roleApi } from '@/api/role'
import type { RoleInfo, RoleReq } from '@/types/api'

const { t } = useI18n()

interface Props {
    modelValue: boolean
    formData: Partial<RoleInfo>
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

// 是否编辑模式
const isEdit = computed(() => !!props.formData.id)

// 表单数据
const form = reactive<RoleReq>({
    name: '',
    title: '',
    description: '',
    data_scope: 1,
    status: 1
})

// 表单验证规则
const rules = computed(() => ({
    name: [
        { required: true, message: t('role.validate.codeRequired'), trigger: 'blur' },
        { min: 2, max: 50, message: t('role.validate.codeRequired'), trigger: 'blur' },
        {
            pattern: /^[a-zA-Z][a-zA-Z0-9_]*$/,
            message: t('role.validate.codeRequired'),
            trigger: 'blur'
        }
    ],
    title: [
        { required: true, message: t('role.validate.nameRequired'), trigger: 'blur' },
        { min: 2, max: 50, message: t('role.validate.nameRequired'), trigger: 'blur' }
    ],
    data_scope: [{ required: true, message: t('common.selectPlaceholder'), trigger: 'change' }]
}))

// 提交加载状态
const submitLoading = ref(false)

// 监听表单数据变化
watch(
    () => props.formData,
    (newData) => {
        if (newData) {
            Object.assign(form, {
                name: newData.name || '',
                title: newData.title || '',
                description: newData.description || '',
                data_scope: newData.data_scope || 1,
                status: newData.status || 1
            })
        }
    },
    { deep: true, immediate: true }
)

// 提交表单
const handleSubmit = async () => {
    if (!formRef.value) return

    try {
        await formRef.value.validate()

        submitLoading.value = true

        if (isEdit.value && props.formData.id) {
            // 编辑
            await roleApi.updateRole(props.formData.id, form)
            ElMessage.success(t('message.updateSuccess'))
        } else {
            // 新增
            await roleApi.createRole(form)
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
.form-tip {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-top: 4px;
    line-height: 1.4;
}

.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
</style>
