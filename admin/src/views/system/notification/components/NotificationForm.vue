<template>
    <el-dialog
        v-model="visible"
        :title="
            form.id
                ? $t('notificationMgmt.editNotification')
                : $t('notificationMgmt.addNotification')
        "
        width="640px"
        :close-on-click-modal="false"
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
                        <el-select
                            v-model="form.type"
                            :placeholder="$t('notificationMgmt.typePlaceholder')"
                            style="width: 100%"
                        >
                            <el-option
                                :label="$t('notificationMgmt.typeOptions.system')"
                                :value="1"
                            />
                            <el-option
                                :label="$t('notificationMgmt.typeOptions.todo')"
                                :value="2"
                            />
                            <el-option
                                :label="$t('notificationMgmt.typeOptions.business')"
                                :value="3"
                            />
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
                            <el-option
                                :label="$t('notificationMgmt.scopeOptions.all')"
                                :value="1"
                            />
                            <el-option
                                :label="$t('notificationMgmt.scopeOptions.specified')"
                                :value="2"
                            />
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

import { notificationApi } from '@/api/notification'
import { useFormDialog } from '@/hooks/useFormDialog'

const { t } = useI18n()

interface NotificationForm {
    id?: number
    title: string
    content: string
    type: number
    target_type: number
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
    useFormDialog<NotificationForm>({
        defaultForm: {
            id: undefined,
            title: '',
            content: '',
            type: 1,
            target_type: 1,
            status: 1
        },
        modelValue: () => props.modelValue,
        onUpdate: (v) => emit('update:modelValue', v),
        onSuccess: () => emit('success'),
        createFn: (data) => notificationApi.create(data),
        updateFn: (id, data) => notificationApi.update(id, data),
        sourceData: () => props.formData as Partial<NotificationForm>
    })

const rules = computed<FormRules>(() => ({
    title: [
        { required: true, message: t('notificationMgmt.validate.titleRequired'), trigger: 'blur' }
    ],
    content: [
        { required: true, message: t('notificationMgmt.validate.contentRequired'), trigger: 'blur' }
    ],
    type: [
        { required: true, message: t('notificationMgmt.validate.typeRequired'), trigger: 'change' }
    ]
}))
</script>
