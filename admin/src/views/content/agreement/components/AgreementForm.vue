<template>
    <el-dialog
        :model-value="modelValue"
        :title="formData.id ? $t('agreementMgmt.editAgreement') : $t('agreementMgmt.addAgreement')"
        width="640px"
        :close-on-click-modal="false"
        @update:model-value="$emit('update:modelValue', $event)"
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
                <div v-if="form.id" class="el-form-item__tip" style="color: var(--el-text-color-placeholder); font-size: 12px; line-height: 1.5; margin-top: 4px;">
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
            <el-button @click="$emit('update:modelValue', false)">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="submitting" @click="handleSubmit">{{ $t('common.confirm') }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import type { FormInstance, FormRules } from 'element-plus'
import { ElMessage } from 'element-plus'
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { agreementApi } from '@/api/agreement'

const { t } = useI18n()

const props = defineProps<{
    modelValue: boolean
    formData: Record<string, any>
}>()

const emit = defineEmits<{
    'update:modelValue': [value: boolean]
    success: []
}>()

const formRef = ref<FormInstance>()
const submitting = ref(false)

const form = reactive({
    id: undefined as number | undefined,
    title: '',
    code: '',
    content: '',
    status: 1
})

const rules = computed<FormRules>(() => ({
    title: [{ required: true, message: t('agreementMgmt.validate.titleRequired'), trigger: 'blur' }],
    code: [
        { required: true, message: t('agreementMgmt.validate.codeRequired'), trigger: 'blur' },
        { pattern: /^[a-zA-Z][a-zA-Z0-9_]*$/, message: t('agreementMgmt.validate.codeFormat'), trigger: 'blur' }
    ],
    content: [{ required: true, message: t('agreementMgmt.validate.contentRequired'), trigger: 'blur' }]
}))

watch(
    () => props.modelValue,
    (val) => {
        if (val && props.formData) {
            Object.assign(form, {
                id: props.formData.id || undefined,
                title: props.formData.title || '',
                code: props.formData.code || '',
                content: props.formData.content || '',
                status: props.formData.status ?? 1
            })
        }
    }
)

const resetForm = () => {
    formRef.value?.resetFields()
    Object.assign(form, {
        id: undefined,
        title: '',
        code: '',
        content: '',
        status: 1
    })
}

const handleSubmit = async () => {
    if (!formRef.value) return
    await formRef.value.validate()

    submitting.value = true
    try {
        if (form.id) {
            await agreementApi.update(form.id, { ...form })
            ElMessage.success(t('message.updateSuccess'))
        } else {
            await agreementApi.create({ ...form })
            ElMessage.success(t('message.createSuccess'))
        }
        emit('update:modelValue', false)
        emit('success')
    } catch (error: any) {
        ElMessage.error(error?.message || t('common.error'))
    } finally {
        submitting.value = false
    }
}
</script>
