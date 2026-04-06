<template>
    <el-dialog
        v-model="visible"
        :title="form.id ? $t('department.editDept') : $t('department.addDept')"
        width="560px"
        :close-on-click-modal="false"
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
            <el-form-item :label="$t('department.parentDept')" prop="parent_id">
                <el-tree-select
                    v-model="form.parent_id"
                    :data="parentOptions"
                    node-key="id"
                    :props="{ label: 'name', children: 'children' }"
                    :placeholder="$t('department.parentPlaceholder')"
                    clearable
                    check-strictly
                    :render-after-expand="false"
                    style="width: 100%"
                />
            </el-form-item>

            <el-form-item :label="$t('department.deptName')" prop="name">
                <el-input
                    v-model="form.name"
                    :placeholder="$t('department.namePlaceholder')"
                    maxlength="100"
                />
            </el-form-item>

            <el-form-item :label="$t('department.deptCode')" prop="code">
                <el-input
                    v-model="form.code"
                    :placeholder="$t('department.codePlaceholder')"
                    maxlength="50"
                />
            </el-form-item>

            <el-row :gutter="20">
                <el-col :span="12">
                    <el-form-item :label="$t('department.leader')" prop="leader">
                        <el-input
                            v-model="form.leader"
                            :placeholder="$t('department.leaderPlaceholder')"
                            maxlength="50"
                        />
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="$t('department.phone')" prop="phone">
                        <el-input
                            v-model="form.phone"
                            :placeholder="$t('department.phonePlaceholder')"
                            maxlength="20"
                        />
                    </el-form-item>
                </el-col>
            </el-row>

            <el-form-item :label="$t('admin.email')" prop="email">
                <el-input
                    v-model="form.email"
                    :placeholder="$t('department.emailPlaceholder')"
                    maxlength="100"
                />
            </el-form-item>

            <el-row :gutter="20">
                <el-col :span="12">
                    <el-form-item :label="$t('common.sort')" prop="sort">
                        <el-input-number
                            v-model="form.sort"
                            :min="0"
                            :max="9999"
                            style="width: 100%"
                        />
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="$t('common.status')" prop="status">
                        <el-radio-group v-model="form.status">
                            <el-radio :value="1">{{ $t('common.enable') }}</el-radio>
                            <el-radio :value="0">{{ $t('common.disable') }}</el-radio>
                        </el-radio-group>
                    </el-form-item>
                </el-col>
            </el-row>

            <el-form-item :label="$t('common.remark')" prop="remark">
                <el-input
                    v-model="form.remark"
                    type="textarea"
                    :rows="3"
                    :placeholder="$t('common.remark')"
                    maxlength="255"
                    show-word-limit
                />
            </el-form-item>
        </el-form>

        <template #footer>
            <el-button @click="handleClose">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="submitting" @click="handleSubmit">{{
                $t('common.confirm')
            }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import type { FormRules } from 'element-plus'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import { departmentApi } from '@/api/department'
import { useFormDialog } from '@/hooks/useFormDialog'

const { t } = useI18n()

interface DepartmentForm {
    id?: number
    parent_id: number
    name: string
    code: string
    leader: string
    phone: string
    email: string
    status: number
    sort: number
    remark: string
}

const props = defineProps<{
    modelValue: boolean
    formData: Record<string, any>
    parentOptions: any[]
}>()

const emit = defineEmits<{
    'update:modelValue': [value: boolean]
    success: []
}>()

const { form, formRef, submitting, visible, handleSubmit, handleClose, resetForm } =
    useFormDialog<DepartmentForm>({
        defaultForm: {
            id: undefined,
            parent_id: 0,
            name: '',
            code: '',
            leader: '',
            phone: '',
            email: '',
            status: 1,
            sort: 0,
            remark: ''
        },
        modelValue: () => props.modelValue,
        onUpdate: (v) => emit('update:modelValue', v),
        onSuccess: () => emit('success'),
        createFn: (data) => departmentApi.create(data),
        updateFn: (id, data) => departmentApi.update(id, data),
        sourceData: () => props.formData as Partial<DepartmentForm>
    })

const rules = computed<FormRules>(() => ({
    name: [{ required: true, message: t('department.validate.nameRequired'), trigger: 'blur' }],
    email: [{ type: 'email', message: t('admin.validate.emailFormat'), trigger: 'blur' }]
}))
</script>
