<template>
    <el-dialog
        v-model="visible"
        :title="form.id ? $t('cronJob.editTask') : $t('cronJob.addTask')"
        width="560px"
        :close-on-click-modal="false"
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
            <el-form-item :label="$t('cronJob.taskName')" prop="name">
                <el-input
                    v-model="form.name"
                    :placeholder="$t('cronJob.namePlaceholder')"
                    maxlength="100"
                />
            </el-form-item>

            <el-form-item :label="$t('cronJob.command')" prop="command">
                <el-input
                    v-model="form.command"
                    :placeholder="$t('cronJob.commandPlaceholder')"
                    maxlength="255"
                />
            </el-form-item>

            <el-form-item :label="$t('cronJob.executionCycle')" prop="cron_expression">
                <CronBuilder v-model="form.cron_expression" />
            </el-form-item>

            <el-form-item :label="$t('cronJob.taskDesc')" prop="description">
                <el-input
                    v-model="form.description"
                    type="textarea"
                    :rows="3"
                    :placeholder="$t('cronJob.descPlaceholder')"
                    maxlength="255"
                    show-word-limit
                />
            </el-form-item>

            <el-form-item :label="$t('common.sort')" prop="sort">
                <el-input-number v-model="form.sort" :min="0" :max="9999" style="width: 100%" />
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

import { cronJobApi } from '@/api/cron-job'
import CronBuilder from '@/components/CronBuilder/index.vue'
import { useFormDialog } from '@/hooks/useFormDialog'

const { t } = useI18n()

interface CronJobForm {
    id?: number
    name: string
    command: string
    cron_expression: string
    description: string
    status: number
    sort: number
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
    useFormDialog<CronJobForm>({
        defaultForm: {
            id: undefined,
            name: '',
            command: '',
            cron_expression: '',
            description: '',
            status: 1,
            sort: 0
        },
        modelValue: () => props.modelValue,
        onUpdate: (v) => emit('update:modelValue', v),
        onSuccess: () => emit('success'),
        createFn: (data) => cronJobApi.create(data),
        updateFn: (id, data) => cronJobApi.update(id, data),
        sourceData: () => {
            const src = props.formData
            if (!src) return null
            return {
                id: src.id || undefined,
                name: src.name || '',
                command: src.command || '',
                cron_expression: src.cron_expression || src.expression || '',
                description: src.description || '',
                status: src.status ?? 1,
                sort: src.sort ?? 0
            }
        }
    })

const rules = computed<FormRules>(() => ({
    name: [{ required: true, message: t('cronJob.validate.nameRequired'), trigger: 'blur' }],
    command: [{ required: true, message: t('cronJob.validate.commandRequired'), trigger: 'blur' }],
    cron_expression: [
        { required: true, message: t('cronJob.validate.cronRequired'), trigger: 'blur' }
    ]
}))
</script>
