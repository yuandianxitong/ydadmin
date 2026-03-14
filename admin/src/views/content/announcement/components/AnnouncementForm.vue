<template>
    <el-dialog
        :model-value="modelValue"
        :title="formData.id ? $t('announcementMgmt.editAnnouncement') : $t('announcementMgmt.addAnnouncement')"
        width="640px"
        :close-on-click-modal="false"
        @update:model-value="$emit('update:modelValue', $event)"
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
            <el-form-item :label="$t('announcementMgmt.announcementTitle')" prop="title">
                <el-input
                    v-model="form.title"
                    :placeholder="$t('announcementMgmt.titlePlaceholder')"
                    maxlength="200"
                    show-word-limit
                />
            </el-form-item>

            <el-row :gutter="20">
                <el-col :span="12">
                    <el-form-item :label="$t('announcementMgmt.announcementType')" prop="type">
                        <el-select v-model="form.type" :placeholder="$t('announcementMgmt.typePlaceholder')" style="width: 100%">
                            <el-option :label="$t('announcementMgmt.typeOptions.notice')" :value="1" />
                            <el-option :label="$t('announcementMgmt.typeOptions.update')" :value="2" />
                            <el-option :label="$t('announcementMgmt.typeOptions.activity')" :value="3" />
                        </el-select>
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="$t('common.sort')" prop="sort">
                        <el-input-number v-model="form.sort" :min="0" :max="9999" style="width: 100%" />
                    </el-form-item>
                </el-col>
            </el-row>

            <el-form-item :label="$t('announcementMgmt.content')" prop="content">
                <el-input
                    v-model="form.content"
                    type="textarea"
                    :rows="8"
                    :placeholder="$t('announcementMgmt.contentPlaceholder')"
                />
            </el-form-item>

            <el-form-item :label="$t('common.status')" prop="status">
                <el-radio-group v-model="form.status">
                    <el-radio :value="1">{{ $t('announcementMgmt.published') }}</el-radio>
                    <el-radio :value="0">{{ $t('announcementMgmt.draft') }}</el-radio>
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

import { announcementApi } from '@/api/announcement'

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
    sort: 0,
    status: 1
})

const rules = computed<FormRules>(() => ({
    title: [{ required: true, message: t('announcementMgmt.validate.titleRequired'), trigger: 'blur' }],
    content: [{ required: true, message: t('announcementMgmt.validate.contentRequired'), trigger: 'blur' }]
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
                sort: props.formData.sort ?? 0,
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
        sort: 0,
        status: 1
    })
}

const handleSubmit = async () => {
    if (!formRef.value) return
    await formRef.value.validate()

    submitting.value = true
    try {
        if (form.id) {
            await announcementApi.update(form.id, { ...form })
            ElMessage.success(t('message.updateSuccess'))
        } else {
            await announcementApi.create({ ...form })
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
