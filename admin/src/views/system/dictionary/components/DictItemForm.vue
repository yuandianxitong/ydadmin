<template>
    <el-dialog
        :model-value="modelValue"
        :title="isEdit ? $t('dictionary.editItem') : $t('dictionary.addItem')"
        width="500px"
        destroy-on-close
        @close="handleClose"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="80px">
            <el-form-item :label="$t('dictionary.label')" prop="label">
                <el-input v-model="form.label" :placeholder="$t('dictionary.labelPlaceholder')" maxlength="100" />
            </el-form-item>
            <el-form-item :label="$t('dictionary.value')" prop="value">
                <el-input v-model="form.value" :placeholder="$t('dictionary.valuePlaceholder')" maxlength="100" />
            </el-form-item>
            <el-form-item :label="$t('dictionary.tagType')" prop="tag_type">
                <el-select v-model="form.tag_type" :placeholder="$t('dictionary.tagTypePlaceholder')" clearable>
                    <el-option :label="$t('dictionary.tagTypeOptions.default')" value="" />
                    <el-option :label="$t('dictionary.tagTypeOptions.success')" value="success" />
                    <el-option :label="$t('dictionary.tagTypeOptions.warning')" value="warning" />
                    <el-option :label="$t('dictionary.tagTypeOptions.danger')" value="danger" />
                    <el-option :label="$t('dictionary.tagTypeOptions.info')" value="info" />
                </el-select>
            </el-form-item>
            <el-form-item :label="$t('common.description')" prop="description">
                <el-input
                    v-model="form.description"
                    type="textarea"
                    :rows="2"
                    :placeholder="$t('dictionary.descPlaceholder')"
                    maxlength="500"
                />
            </el-form-item>
            <el-form-item :label="$t('common.sort')" prop="sort">
                <el-input-number v-model="form.sort" :min="0" :max="9999" />
            </el-form-item>
            <el-form-item :label="$t('common.status')" prop="status">
                <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
            </el-form-item>
        </el-form>

        <template #footer>
            <el-button @click="handleClose">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="loading" @click="handleSubmit">{{ $t('common.confirm') }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { ElForm, ElMessage } from 'element-plus'
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { dictionaryApi } from '@/api/dictionary'

const { t } = useI18n()

interface Props {
    modelValue: boolean
    formData: Record<string, any>
    dictionaryId: number
}

interface Emits {
    (e: 'update:modelValue', value: boolean): void
    (e: 'success'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const formRef = ref<InstanceType<typeof ElForm>>()
const loading = ref(false)

const isEdit = computed(() => !!props.formData?.id)

const form = reactive({
    label: '',
    value: '',
    tag_type: '',
    description: '',
    sort: 0,
    status: 1
})

const rules = {
    label: [{ required: true, message: t('dictionary.validate.labelRequired'), trigger: 'blur' }],
    value: [{ required: true, message: t('dictionary.validate.valueRequired'), trigger: 'blur' }]
}

watch(
    () => props.modelValue,
    (val) => {
        if (val && props.formData) {
            Object.assign(form, {
                label: props.formData.label || '',
                value: props.formData.value || '',
                tag_type: props.formData.tag_type || '',
                description: props.formData.description || '',
                sort: props.formData.sort ?? 0,
                status: props.formData.status ?? 1
            })
        }
    }
)

const handleClose = () => {
    emit('update:modelValue', false)
}

const handleSubmit = async () => {
    if (!formRef.value) return
    await formRef.value.validate()
    loading.value = true
    try {
        if (isEdit.value) {
            await dictionaryApi.updateItem(props.formData.id, { ...form })
            ElMessage.success(t('message.updateSuccess'))
        } else {
            await dictionaryApi.createItem({ dictionary_id: props.dictionaryId, ...form })
            ElMessage.success(t('message.createSuccess'))
        }
        emit('success')
        handleClose()
    } catch (error) {
        console.error('保存失败:', error)
    } finally {
        loading.value = false
    }
}
</script>
