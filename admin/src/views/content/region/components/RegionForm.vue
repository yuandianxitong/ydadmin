<template>
    <el-dialog
        v-model="visible"
        :title="form.id ? $t('regionMgmt.editRegion') : $t('regionMgmt.addRegion')"
        width="540px"
        :close-on-click-modal="false"
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
            <el-form-item :label="$t('regionMgmt.parentRegion')">
                <el-input
                    :model-value="
                        form.parent_id === 0
                            ? $t('regionMgmt.topLevel')
                            : formData.parent_name || $t('regionMgmt.topLevel')
                    "
                    disabled
                />
            </el-form-item>

            <el-form-item :label="$t('regionMgmt.regionName')" prop="name">
                <el-input
                    v-model="form.name"
                    :placeholder="$t('regionMgmt.namePlaceholder')"
                    maxlength="100"
                    show-word-limit
                />
            </el-form-item>

            <el-form-item :label="$t('regionMgmt.regionCode')" prop="code">
                <el-input
                    v-model="form.code"
                    :placeholder="$t('regionMgmt.codePlaceholder')"
                    maxlength="20"
                />
            </el-form-item>

            <el-form-item :label="$t('common.sort')" prop="sort">
                <el-input-number v-model="form.sort" :min="0" :max="9999" />
            </el-form-item>

            <el-form-item :label="$t('common.status')" prop="status">
                <el-radio-group v-model="form.status">
                    <el-radio :value="1">{{ $t('common.enable') }}</el-radio>
                    <el-radio :value="0">{{ $t('common.disable') }}</el-radio>
                </el-radio-group>
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

import { regionApi } from '@/api/region'
import { useFormDialog } from '@/hooks/useFormDialog'

const { t } = useI18n()

interface RegionFormData {
    id?: number
    parent_id: number
    name: string
    code: string
    level: number
    sort: number
    status: number
}

const props = defineProps<{
    modelValue: boolean
    formData: Record<string, any>
}>()

const emit = defineEmits<{
    'update:modelValue': [value: boolean]
    success: []
}>()

const { form, formRef, submitting, visible, handleSubmit, handleClose, resetForm } =
    useFormDialog<RegionFormData>({
        defaultForm: {
            id: undefined,
            parent_id: 0,
            name: '',
            code: '',
            level: 1,
            sort: 0,
            status: 1
        },
        modelValue: () => props.modelValue,
        onUpdate: (v) => emit('update:modelValue', v),
        onSuccess: () => emit('success'),
        createFn: (data) => regionApi.create(data),
        updateFn: (id, data) => regionApi.update(id, data),
        sourceData: () => props.formData as Partial<RegionFormData>
    })

const rules = computed<FormRules>(() => ({
    name: [{ required: true, message: t('regionMgmt.validate.nameRequired'), trigger: 'blur' }],
    code: [{ required: true, message: t('regionMgmt.validate.codeRequired'), trigger: 'blur' }]
}))
</script>
