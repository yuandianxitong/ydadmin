<template>
    <el-dialog
        v-model="visible"
        :title="form.id ? $t('dictionary.editItem') : $t('dictionary.addItem')"
        width="500px"
        destroy-on-close
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="80px">
            <el-form-item :label="$t('dictionary.label')" prop="label">
                <el-input
                    v-model="form.label"
                    :placeholder="$t('dictionary.labelPlaceholder')"
                    maxlength="100"
                />
            </el-form-item>
            <el-form-item :label="$t('dictionary.value')" prop="value">
                <el-input
                    v-model="form.value"
                    :placeholder="$t('dictionary.valuePlaceholder')"
                    maxlength="100"
                />
            </el-form-item>
            <el-form-item :label="$t('dictionary.tagType')" prop="tag_type">
                <el-select
                    v-model="form.tag_type"
                    :placeholder="$t('dictionary.tagTypePlaceholder')"
                    clearable
                >
                    <el-option :label="$t('dictionary.tagTypeOptions.default')" value="" />
                    <el-option :label="$t('dictionary.tagTypeOptions.success')" value="success" />
                    <el-option :label="$t('dictionary.tagTypeOptions.warning')" value="warning" />
                    <el-option :label="$t('dictionary.tagTypeOptions.danger')" value="danger" />
                    <el-option :label="$t('dictionary.tagTypeOptions.info')" value="info" />
                </el-select>
            </el-form-item>
            <el-form-item :label="$t('common.description')" prop="description">
                <el-input
                    v-model="form.description"
                    type="textarea"
                    :rows="2"
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

interface DictionaryItemForm {
    id?: number
    label: string
    value: string
    tag_type: string
    description: string
    sort: number
    status: number
}

interface Props {
    modelValue: boolean
    formData: Record<string, any>
    dictionaryId: number
}

interface Emits {
    (e: 'update:modelValue', value: boolean): void
    (e: 'success'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { form, formRef, submitting, visible, handleSubmit, handleClose, resetForm } =
    useFormDialog<DictionaryItemForm>({
        defaultForm: {
            id: undefined,
            label: '',
            value: '',
            tag_type: '',
            description: '',
            sort: 0,
            status: 1
        },
        modelValue: () => props.modelValue,
        onUpdate: (v) => emit('update:modelValue', v),
        onSuccess: () => emit('success'),
        // 创建时需要附带 dictionary_id（接口必填字段）
        createFn: (data) =>
            dictionaryApi.createItem({ dictionary_id: props.dictionaryId, ...data } as any),
        updateFn: (id, data) => dictionaryApi.updateItem(id, data),
        sourceData: () => props.formData as Partial<DictionaryItemForm>
    })

const rules = computed<FormRules>(() => ({
    label: [{ required: true, message: t('dictionary.validate.labelRequired'), trigger: 'blur' }],
    value: [{ required: true, message: t('dictionary.validate.valueRequired'), trigger: 'blur' }]
}))
</script>
