<template>
    <el-dialog
        :model-value="modelValue"
        :title="formData.id ? $t('versionMgmt.editVersion') : $t('versionMgmt.addVersion')"
        width="640px"
        :close-on-click-modal="false"
        @update:model-value="$emit('update:modelValue', $event)"
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
            <el-row :gutter="20">
                <el-col :span="12">
                    <el-form-item :label="$t('versionMgmt.platform')" prop="platform">
                        <el-select v-model="form.platform" :placeholder="$t('versionMgmt.platformPlaceholder')" style="width: 100%">
                            <el-option :label="$t('versionMgmt.platformOptions.android')" value="android" />
                            <el-option :label="$t('versionMgmt.platformOptions.ios')" value="ios" />
                            <el-option :label="$t('versionMgmt.platformOptions.harmony')" value="harmony" />
                        </el-select>
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="$t('versionMgmt.version')" prop="version">
                        <el-input
                            v-model="form.version"
                            :placeholder="$t('versionMgmt.versionPlaceholder')"
                        />
                    </el-form-item>
                </el-col>
            </el-row>

            <el-row :gutter="20">
                <el-col :span="12">
                    <el-form-item :label="$t('versionMgmt.versionCode')" prop="version_code">
                        <el-input-number
                            v-model="form.version_code"
                            :min="1"
                            :placeholder="$t('versionMgmt.versionCodePlaceholder')"
                            style="width: 100%"
                        />
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="$t('versionMgmt.forceUpdate')" prop="force_update">
                        <el-switch
                            v-model="form.force_update"
                            :active-value="1"
                            :inactive-value="0"
                        />
                    </el-form-item>
                </el-col>
            </el-row>

            <el-form-item :label="$t('versionMgmt.downloadUrl')" prop="download_url">
                <el-input
                    v-model="form.download_url"
                    :placeholder="$t('versionMgmt.downloadUrlPlaceholder')"
                />
            </el-form-item>

            <el-form-item :label="$t('common.description')" prop="description">
                <el-input
                    v-model="form.description"
                    type="textarea"
                    :rows="4"
                    :placeholder="$t('versionMgmt.descriptionPlaceholder')"
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

import { versionApi } from '@/api/version'

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
    platform: '',
    version: '',
    version_code: 1,
    download_url: '',
    description: '',
    force_update: 0,
    status: 1
})

const rules = computed<FormRules>(() => ({
    platform: [{ required: true, message: t('versionMgmt.validate.platformRequired'), trigger: 'change' }],
    version: [{ required: true, message: t('versionMgmt.validate.versionRequired'), trigger: 'blur' }],
    version_code: [{ required: true, message: t('versionMgmt.validate.versionCodeRequired'), trigger: 'blur' }],
    download_url: [{ required: true, message: t('versionMgmt.validate.downloadUrlRequired'), trigger: 'blur' }]
}))

watch(
    () => props.modelValue,
    (val) => {
        if (val && props.formData) {
            Object.assign(form, {
                id: props.formData.id || undefined,
                platform: props.formData.platform || '',
                version: props.formData.version || '',
                version_code: props.formData.version_code ?? 1,
                download_url: props.formData.download_url || '',
                description: props.formData.description || '',
                force_update: props.formData.force_update ?? 0,
                status: props.formData.status ?? 1
            })
        }
    }
)

const resetForm = () => {
    formRef.value?.resetFields()
    Object.assign(form, {
        id: undefined,
        platform: '',
        version: '',
        version_code: 1,
        download_url: '',
        description: '',
        force_update: 0,
        status: 1
    })
}

const handleSubmit = async () => {
    if (!formRef.value) return
    await formRef.value.validate()

    submitting.value = true
    try {
        if (form.id) {
            await versionApi.update(form.id, { ...form })
            ElMessage.success(t('message.updateSuccess'))
        } else {
            await versionApi.create({ ...form })
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
