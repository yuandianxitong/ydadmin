<template>
    <el-dialog
        :model-value="modelValue"
        :title="formData.id ? $t('regionMgmt.editRegion') : $t('regionMgmt.addRegion')"
        width="540px"
        :close-on-click-modal="false"
        @update:model-value="$emit('update:modelValue', $event)"
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
            <el-form-item :label="$t('regionMgmt.parentRegion')">
                <el-input
                    :model-value="form.parent_id === 0 ? $t('regionMgmt.topLevel') : (formData.parent_name || $t('regionMgmt.topLevel'))"
                    disabled
                />
            </el-form-item>

            <el-form-item :label="$t('regionMgmt.regionName')" prop="name">
                <el-input
                    v-model="form.name"
                    :placeholder="$t('regionMgmt.namePlaceholder')"
                    maxlength="100"
                    show-word-limit
                />
            </el-form-item>

            <el-form-item :label="$t('regionMgmt.regionCode')" prop="code">
                <el-input
                    v-model="form.code"
                    :placeholder="$t('regionMgmt.codePlaceholder')"
                    maxlength="20"
                />
            </el-form-item>

            <el-form-item :label="$t('common.sort')" prop="sort">
                <el-input-number v-model="form.sort" :min="0" :max="9999" />
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

import { regionApi } from '@/api/region'

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
    parent_id: 0,
    name: '',
    code: '',
    level: 1,
    sort: 0,
    status: 1
})

const rules = computed<FormRules>(() => ({
    name: [{ required: true, message: t('regionMgmt.validate.nameRequired'), trigger: 'blur' }],
    code: [{ required: true, message: t('regionMgmt.validate.codeRequired'), trigger: 'blur' }]
}))

watch(
    () => props.modelValue,
    (val) => {
        if (val && props.formData) {
            Object.assign(form, {
                id: props.formData.id || undefined,
                parent_id: props.formData.parent_id ?? 0,
                name: props.formData.name || '',
                code: props.formData.code || '',
                level: props.formData.level ?? 1,
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
        parent_id: 0,
        name: '',
        code: '',
        level: 1,
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
            await regionApi.update(form.id, { ...form })
            ElMessage.success(t('message.updateSuccess'))
        } else {
            await regionApi.create({ ...form })
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
