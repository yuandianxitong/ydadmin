<template>
    <el-dialog
        v-model="visible"
        :title="form.id ? $t('messageTemplate.editTemplate') : $t('messageTemplate.addTemplate')"
        width="700px"
        destroy-on-close
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="120px">
            <el-form-item :label="$t('messageTemplate.templateName')" prop="name">
                <el-input
                    v-model="form.name"
                    :placeholder="$t('messageTemplate.namePlaceholder')"
                />
            </el-form-item>
            <el-form-item :label="$t('messageTemplate.templateCode')" prop="code">
                <el-input
                    v-model="form.code"
                    :placeholder="$t('messageTemplate.codePlaceholder')"
                    :disabled="!!form.id"
                />
            </el-form-item>
            <el-form-item :label="$t('messageTemplate.remark')">
                <el-input
                    v-model="form.remark"
                    type="textarea"
                    :rows="2"
                    :placeholder="$t('messageTemplate.remarkPlaceholder')"
                />
            </el-form-item>
            <el-form-item :label="$t('common.status')">
                <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
            </el-form-item>

            <el-divider content-position="left">{{
                $t('messageTemplate.channelConfig')
            }}</el-divider>

            <!-- 短信通道 -->
            <el-form-item :label="$t('messageTemplate.smsChannel')">
                <el-switch v-model="form.sms_enabled" :active-value="1" :inactive-value="0" />
            </el-form-item>
            <template v-if="form.sms_enabled">
                <el-form-item :label="$t('messageTemplate.smsTemplateId')">
                    <el-input
                        v-model="form.sms_template_id"
                        :placeholder="$t('messageTemplate.smsTemplateIdPlaceholder')"
                    />
                </el-form-item>
                <el-form-item :label="$t('messageTemplate.smsContent')">
                    <el-input
                        v-model="form.sms_content"
                        type="textarea"
                        :rows="2"
                        :placeholder="$t('messageTemplate.smsContentPlaceholder')"
                    />
                </el-form-item>
            </template>

            <!-- 公众号通道 -->
            <el-form-item :label="$t('messageTemplate.officialChannel')">
                <el-switch
                    v-model="form.wechat_official_enabled"
                    :active-value="1"
                    :inactive-value="0"
                />
            </el-form-item>
            <template v-if="form.wechat_official_enabled">
                <el-form-item :label="$t('messageTemplate.officialTemplateId')">
                    <el-input
                        v-model="form.wechat_official_template_id"
                        :placeholder="$t('messageTemplate.officialTemplateIdPlaceholder')"
                    />
                </el-form-item>
                <el-form-item :label="$t('messageTemplate.officialUrl')">
                    <el-input
                        v-model="form.wechat_official_url"
                        :placeholder="$t('messageTemplate.officialUrlPlaceholder')"
                    />
                </el-form-item>
            </template>

            <!-- 小程序通道 -->
            <el-form-item :label="$t('messageTemplate.miniappChannel')">
                <el-switch
                    v-model="form.wechat_mini_enabled"
                    :active-value="1"
                    :inactive-value="0"
                />
            </el-form-item>
            <template v-if="form.wechat_mini_enabled">
                <el-form-item :label="$t('messageTemplate.miniappTemplateId')">
                    <el-input
                        v-model="form.wechat_mini_template_id"
                        :placeholder="$t('messageTemplate.miniappTemplateIdPlaceholder')"
                    />
                </el-form-item>
                <el-form-item :label="$t('messageTemplate.miniappPage')">
                    <el-input
                        v-model="form.wechat_mini_page"
                        :placeholder="$t('messageTemplate.miniappPagePlaceholder')"
                    />
                </el-form-item>
            </template>
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

import { messageTemplateApi } from '@/api/message'
import { useFormDialog } from '@/hooks/useFormDialog'

const { t } = useI18n()

interface MessageTemplateForm {
    id?: number
    name: string
    code: string
    remark: string
    status: number
    sms_enabled: number
    sms_template_id: string
    sms_content: string
    wechat_official_enabled: number
    wechat_official_template_id: string
    wechat_official_url: string
    wechat_mini_enabled: number
    wechat_mini_template_id: string
    wechat_mini_page: string
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
    useFormDialog<MessageTemplateForm>({
        defaultForm: {
            id: undefined,
            name: '',
            code: '',
            remark: '',
            status: 1,
            sms_enabled: 0,
            sms_template_id: '',
            sms_content: '',
            wechat_official_enabled: 0,
            wechat_official_template_id: '',
            wechat_official_url: '',
            wechat_mini_enabled: 0,
            wechat_mini_template_id: '',
            wechat_mini_page: ''
        },
        modelValue: () => props.modelValue,
        onUpdate: (v) => emit('update:modelValue', v),
        onSuccess: () => emit('success'),
        createFn: (data) => messageTemplateApi.create(data),
        updateFn: (id, data) => messageTemplateApi.update(id, data),
        sourceData: () => props.formData as Partial<MessageTemplateForm>
    })

const rules = computed<FormRules>(() => ({
    name: [
        { required: true, message: t('messageTemplate.validate.nameRequired'), trigger: 'blur' }
    ],
    code: [
        { required: true, message: t('messageTemplate.validate.codeRequired'), trigger: 'blur' },
        {
            pattern: /^[a-z][a-z0-9_]*$/,
            message: t('messageTemplate.codeFormatRule'),
            trigger: 'blur'
        }
    ]
}))
</script>
