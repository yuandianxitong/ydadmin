<template>
    <el-dialog
        v-model="visible"
        :title="
            form.id
                ? $t('announcementMgmt.editAnnouncement')
                : $t('announcementMgmt.addAnnouncement')
        "
        width="640px"
        :close-on-click-modal="false"
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
                        <el-select
                            v-model="form.type"
                            :placeholder="$t('announcementMgmt.typePlaceholder')"
                            style="width: 100%"
                        >
                            <el-option
                                :label="$t('announcementMgmt.typeOptions.notice')"
                                :value="1"
                            />
                            <el-option
                                :label="$t('announcementMgmt.typeOptions.update')"
                                :value="2"
                            />
                            <el-option
                                :label="$t('announcementMgmt.typeOptions.activity')"
                                :value="3"
                            />
                        </el-select>
                    </el-form-item>
                </el-col>
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

import { announcementApi } from '@/api/announcement'
import { useFormDialog } from '@/hooks/useFormDialog'

const { t } = useI18n()

interface AnnouncementForm {
    id?: number
    title: string
    content: string
    type: number
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
    useFormDialog<AnnouncementForm>({
        defaultForm: {
            id: undefined,
            title: '',
            content: '',
            type: 1,
            sort: 0,
            status: 1
        },
        modelValue: () => props.modelValue,
        onUpdate: (v) => emit('update:modelValue', v),
        onSuccess: () => emit('success'),
        createFn: (data) => announcementApi.create(data),
        updateFn: (id, data) => announcementApi.update(id, data),
        sourceData: () => props.formData as Partial<AnnouncementForm>
    })

const rules = computed<FormRules>(() => ({
    title: [
        { required: true, message: t('announcementMgmt.validate.titleRequired'), trigger: 'blur' }
    ],
    content: [
        { required: true, message: t('announcementMgmt.validate.contentRequired'), trigger: 'blur' }
    ]
}))
</script>
