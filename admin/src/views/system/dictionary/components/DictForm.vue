<template>
    <el-dialog
        v-model="visible"
        :title="form.id ? $t('dictionary.editDict') : $t('dictionary.addDict')"
        width="500px"
        destroy-on-close
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="80px">
            <el-form-item :label="$t('dictionary.dictName')" prop="name">
                <el-input
                    v-model="form.name"
                    :placeholder="$t('dictionary.namePlaceholder')"
                    maxlength="100"
                />
            </el-form-item>
            <el-form-item :label="$t('dictionary.dictCode')" prop="code">
                <el-input
                    v-model="form.code"
                    :placeholder="$t('dictionary.codePlaceholder')"
                    maxlength="100"
                    :disabled="!!form.id"
                />
            </el-form-item>
            <el-form-item :label="$t('common.description')" prop="description">
                <el-input
                    v-model="form.description"
                    type="textarea"
                    :rows="3"
                    :placeholder="$t('dictionary.descPlaceholder')"
                    maxlength="500"
                />
            </el-form-item>
            <el-form-item :label="$t('common.sort')" prop="sort">
                <el-input-number v-model="form.sort" :min="0" :max="9999" />
            </el-form-item>
            <el-form-item :label="$t('common.status')" prop="status">
                <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
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

import { dictionaryApi } from '@/api/dictionary'
import { useFormDialog } from '@/hooks/useFormDialog'

const { t } = useI18n()

interface DictionaryForm {
    id?: number
    name: string
    code: string
    description: string
    sort: number
    status: number
}

interface Props {
    modelValue: boolean
    formData: Record<string, any>
}

interface Emits {
    (e: 'update:modelValue', value: boolean): void
    (e: 'success'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { form, formRef, submitting, visible, handleSubmit, handleClose, resetForm } =
    useFormDialog<DictionaryForm>({
        defaultForm: {
            id: undefined,
            name: '',
            code: '',
            description: '',
            sort: 0,
            status: 1
        },
        modelValue: () => props.modelValue,
        onUpdate: (v) => emit('update:modelValue', v),
        onSuccess: () => emit('success'),
        createFn: (data) => dictionaryApi.create(data),
        updateFn: (id, data) => dictionaryApi.update(id, data),
        sourceData: () => props.formData as Partial<DictionaryForm>
    })

const rules = computed<FormRules>(() => ({
    name: [{ required: true, message: t('dictionary.validate.nameRequired'), trigger: 'blur' }],
    code: [
        { required: true, message: t('dictionary.validate.codeRequired'), trigger: 'blur' },
        {
            pattern: /^[a-zA-Z0-9_-]+$/,
            message: t('dictionary.validate.codeFormat'),
            trigger: 'blur'
        }
    ]
}))
</script>
