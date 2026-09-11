<template>
    <el-dialog
        v-model="visible"
        :title="form.id ? $t('role.editRole') : $t('role.addRole')"
        width="600px"
        :close-on-click-modal="false"
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
            <el-form-item :label="$t('role.roleCode')" prop="name">
                <el-input
                    v-model="form.name"
                    :placeholder="$t('role.codePlaceholder')"
                    :disabled="!!form.id"
                />
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

            <el-form-item v-if="form.data_scope === 5" :label="$t('role.deptScope')" prop="dept_ids">
                <el-tree-select
                    v-model="form.dept_ids"
                    :data="departmentOptions"
                    node-key="id"
                    :props="{ label: 'name' }"
                    :placeholder="$t('role.deptScopePlaceholder')"
                    multiple
                    show-checkbox
                    check-strictly
                    clearable
                    style="width: 100%"
                />
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

<script setup lang="ts" name="RoleForm">
import type { FormRules } from 'element-plus'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { departmentApi } from '@/api/department'
import { roleApi } from '@/api/role'
import { useFormDialog } from '@/hooks/useFormDialog'
import type { RoleInfo, RoleReq } from '@/types/system'

const { t } = useI18n()

type RoleFormData = RoleReq & { id?: number }

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

const { form, formRef, submitting, visible, handleSubmit, handleClose, resetForm } =
    useFormDialog<RoleFormData>({
        defaultForm: {
            id: undefined,
            name: '',
            title: '',
            description: '',
            data_scope: 1,
            dept_ids: [] as number[],
            status: 1
        },
        modelValue: () => props.modelValue,
        onUpdate: (v) => emit('update:modelValue', v),
        onSuccess: () => emit('success'),
        createFn: (data) => roleApi.createRole(data),
        updateFn: (id, data) => roleApi.updateRole(id, data),
        sourceData: () => props.formData as Partial<RoleFormData>
    })

// 自定义数据权限（data_scope=5）的部门树：弹窗第一次打开时加载
interface DeptNode {
    id: number
    name: string
    children?: DeptNode[]
}
const departmentOptions = ref<DeptNode[]>([])
watch(
    () => props.modelValue,
    async (open) => {
        if (open && departmentOptions.value.length === 0) {
            const response = await departmentApi.getOptions()
            departmentOptions.value = response.data as unknown as DeptNode[]
        }
    },
    { immediate: true }
)

// 表单验证规则
const rules = computed<FormRules>(() => ({
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
    data_scope: [{ required: true, message: t('common.selectPlaceholder'), trigger: 'change' }],
    dept_ids: [
        {
            validator: (_rule: unknown, value: number[] | undefined, callback: (error?: Error) => void) =>
                form.data_scope === 5 && (!value || value.length === 0)
                    ? callback(new Error(t('role.deptScopeRequired')))
                    : callback(),
            trigger: 'change'
        }
    ]
}))
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
