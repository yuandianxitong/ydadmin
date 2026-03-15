<template>
    <el-dialog
        :model-value="modelValue"
        :title="formData.id ? $t('cronJob.editTask') : $t('cronJob.addTask')"
        width="560px"
        :close-on-click-modal="false"
        @update:model-value="$emit('update:modelValue', $event)"
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
            <el-form-item :label="$t('cronJob.taskName')" prop="name">
                <el-input v-model="form.name" :placeholder="$t('cronJob.namePlaceholder')" maxlength="100" />
            </el-form-item>

            <el-form-item :label="$t('cronJob.command')" prop="command">
                <el-input
                    v-model="form.command"
                    :placeholder="$t('cronJob.commandPlaceholder')"
                    maxlength="255"
                />
            </el-form-item>

            <el-form-item :label="$t('cronJob.cronExpression')" prop="expression">
                <CronBuilder v-model="form.expression" />
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

import { cronJobApi } from '@/api/cron-job'
import CronBuilder from '@/components/CronBuilder/index.vue'

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
    name: '',
    command: '',
    expression: '',
    description: '',
    status: 1,
    sort: 0
})

const rules = computed<FormRules>(() => ({
    name: [{ required: true, message: t('cronJob.validate.nameRequired'), trigger: 'blur' }],
    command: [{ required: true, message: t('cronJob.validate.commandRequired'), trigger: 'blur' }],
    expression: [{ required: true, message: t('cronJob.validate.cronRequired'), trigger: 'blur' }]
}))

watch(
    () => props.modelValue,
    (val) => {
        if (val && props.formData) {
            Object.assign(form, {
                id: props.formData.id || undefined,
                name: props.formData.name || '',
                command: props.formData.command || '',
                expression: props.formData.cron_expression || props.formData.expression || '',
                description: props.formData.description || '',
                status: props.formData.status ?? 1,
                sort: props.formData.sort ?? 0
            })
        }
    }
)

const resetForm = () => {
    formRef.value?.resetFields()
    Object.assign(form, {
        id: undefined,
        name: '',
        command: '',
        expression: '',
        description: '',
        status: 1,
        sort: 0
    })
}

const handleSubmit = async () => {
    if (!formRef.value) return
    await formRef.value.validate()

    submitting.value = true
    try {
        const { expression, id, ...rest } = form
        const payload = { ...rest, cron_expression: expression }
        if (id) {
            await cronJobApi.update(id, payload)
            ElMessage.success(t('message.updateSuccess'))
        } else {
            await cronJobApi.create(payload)
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
