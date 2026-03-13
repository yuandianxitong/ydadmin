<template>
    <el-dialog
        :model-value="modelValue"
        :title="formData.id ? $t('notificationMgmt.editNotification') : $t('notificationMgmt.addNotification')"
        width="640px"
        :close-on-click-modal="false"
        @update:model-value="$emit('update:modelValue', $event)"
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
            <el-form-item :label="$t('notificationMgmt.notificationTitle')" prop="title">
                <el-input
                    v-model="form.title"
                    :placeholder="$t('notificationMgmt.titlePlaceholder')"
                    maxlength="200"
                    show-word-limit
                />
            </el-form-item>

            <el-row :gutter="20">
                <el-col :span="12">
                    <el-form-item :label="$t('notificationMgmt.notificationType')" prop="type">
                        <el-select v-model="form.type" :placeholder="$t('notificationMgmt.typePlaceholder')" style="width: 100%">
                            <el-option :label="$t('notificationMgmt.typeOptions.system')" :value="1" />
                            <el-option :label="$t('notificationMgmt.typeOptions.todo')" :value="2" />
                            <el-option :label="$t('notificationMgmt.typeOptions.business')" :value="3" />
                        </el-select>
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="$t('notificationMgmt.targetScope')" prop="target_type">
                        <el-select
                            v-model="form.target_type"
                            :placeholder="$t('common.selectPlaceholder')"
                            style="width: 100%"
                        >
                            <el-option :label="$t('notificationMgmt.scopeOptions.all')" :value="1" />
                            <el-option :label="$t('notificationMgmt.scopeOptions.specified')" :value="2" />
                        </el-select>
                    </el-form-item>
                </el-col>
            </el-row>

            <el-form-item :label="$t('notificationMgmt.content')" prop="content">
                <el-input
                    v-model="form.content"
                    type="textarea"
                    :rows="6"
                    :placeholder="$t('notificationMgmt.contentPlaceholder')"
                />
            </el-form-item>

            <el-form-item :label="$t('common.status')" prop="status">
                <el-radio-group v-model="form.status">
                    <el-radio :value="1">{{ $t('notificationMgmt.publish') }}</el-radio>
                    <el-radio :value="0">{{ $t('notificationMgmt.draft') }}</el-radio>
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

import { notificationApi } from '@/api/notification'

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
    content: '',
    type: 1,
    target_type: 1,
    status: 1
})

const rules = computed<FormRules>(() => ({
    title: [{ required: true, message: t('notificationMgmt.validate.titleRequired'), trigger: 'blur' }],
    content: [{ required: true, message: t('notificationMgmt.validate.contentRequired'), trigger: 'blur' }],
    type: [{ required: true, message: t('notificationMgmt.validate.typeRequired'), trigger: 'change' }]
}))

watch(
    () => props.modelValue,
    (val) => {
        if (val && props.formData) {
            Object.assign(form, {
                id: props.formData.id || undefined,
                title: props.formData.title || '',
                content: props.formData.content || '',
                type: props.formData.type ?? 1,
                target_type: props.formData.target_type ?? 1,
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
        content: '',
        type: 1,
        target_type: 1,
        status: 1
    })
}

const handleSubmit = async () => {
    if (!formRef.value) return
    await formRef.value.validate()

    submitting.value = true
    try {
        if (form.id) {
            await notificationApi.update(form.id, { ...form })
            ElMessage.success(t('message.updateSuccess'))
        } else {
            await notificationApi.create({ ...form })
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
