<template>
    <el-dialog
        v-model="visible"
        :title="form.id ? $t('agreementMgmt.editAgreement') : $t('agreementMgmt.addAgreement')"
        width="640px"
        :close-on-click-modal="false"
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
            <el-form-item :label="$t('agreementMgmt.agreementTitle')" prop="title">
                <el-input
                    v-model="form.title"
                    :placeholder="$t('agreementMgmt.titlePlaceholder')"
                    maxlength="200"
                    show-word-limit
                />
            </el-form-item>

            <el-form-item :label="$t('agreementMgmt.agreementCode')" prop="code">
                <el-input
                    v-model="form.code"
                    :placeholder="$t('agreementMgmt.codePlaceholder')"
                    :disabled="!!form.id"
                    maxlength="100"
                />
                <div
                    v-if="form.id"
                    class="el-form-item__tip"
                    style="
                        color: var(--el-text-color-placeholder);
                        font-size: 12px;
                        line-height: 1.5;
                        margin-top: 4px;
                    "
                >
                    {{ $t('agreementMgmt.codeDisabledTip') }}
                </div>
            </el-form-item>

            <el-form-item :label="$t('agreementMgmt.content')" prop="content">
                <el-input
                    v-model="form.content"
                    type="textarea"
                    :rows="10"
                    :placeholder="$t('agreementMgmt.contentPlaceholder')"
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

import { agreementApi } from '@/api/agreement'
import { useFormDialog } from '@/hooks/useFormDialog'

const { t } = useI18n()

interface AgreementFormData {
    id?: number
    title: string
    code: string
    content: string
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
    useFormDialog<AgreementFormData>({
        defaultForm: {
            id: undefined,
            title: '',
            code: '',
            content: '',
            status: 1
        },
        modelValue: () => props.modelValue,
        onUpdate: (v) => emit('update:modelValue', v),
        onSuccess: () => emit('success'),
        createFn: (data) => agreementApi.create(data),
        updateFn: (id, data) => agreementApi.update(id, data),
        sourceData: () => props.formData as Partial<AgreementFormData>
    })

const rules = computed<FormRules>(() => ({
    title: [
        { required: true, message: t('agreementMgmt.validate.titleRequired'), trigger: 'blur' }
    ],
    code: [
        { required: true, message: t('agreementMgmt.validate.codeRequired'), trigger: 'blur' },
        {
            pattern: /^[a-zA-Z][a-zA-Z0-9_]*$/,
            message: t('agreementMgmt.validate.codeFormat'),
            trigger: 'blur'
        }
    ],
    content: [
        { required: true, message: t('agreementMgmt.validate.contentRequired'), trigger: 'blur' }
    ]
}))
</script>
