<!-- 由代码生成器生成，可按需修改。 -->
<template>
    <el-dialog
        v-model="visible"
        :title="form.id ? '编辑生成器夹具表二（无状态列/图片列/创建人列）' : '新增生成器夹具表二（无状态列/图片列/创建人列）'"
        width="600px"
        destroy-on-close
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
            <el-form-item label="名称" prop="name">
                <el-input v-model="form.name" placeholder="请输入名称" maxlength="100" clearable />
            </el-form-item>

            <el-form-item label="是否精选" prop="is_featured">
                <el-switch v-model="form.is_featured" />
            </el-form-item>

            <el-form-item label="扩展配置" prop="settings">
                <el-input v-model="form.settings" type="textarea" :rows="6" placeholder="请输入扩展配置（JSON 格式）" />
            </el-form-item>

            <el-form-item label="联系邮箱" prop="contact_email">
                <el-input v-model="form.contact_email" placeholder="请输入联系邮箱" maxlength="100" clearable />
            </el-form-item>

            <el-form-item label="排序" prop="sort">
                <el-input-number
                    v-model="form.sort"
                    :min="0"
                    style="width: 100%"
                />
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

import { genCategoryApi } from '@/api/gen-category'
import { useFormDialog } from '@/hooks/useFormDialog'

const { t } = useI18n()

interface GenCategoryFormData {
    id?: number
    name: string
    is_featured?: boolean
    settings?: string
    contact_email?: string
    sort?: number
}

interface Props {
    modelValue: boolean
    formData: Record<string, any>
}

interface Emits {
    (e: 'update:modelValue', value: boolean): void
    (e: 'success'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

/** 表中的 json 列：表单里以 JSON 文本编辑，打开编辑时解码、提交前编码回对象 */
const JSON_FIELDS = ['settings'] as const

function decodeJsonFields(row: Record<string, any>): Record<string, any> {
    const data: Record<string, any> = { ...row }
    for (const field of JSON_FIELDS) {
        const value = data[field]
        if (value === null || value === undefined) {
            data[field] = ''
        } else if (typeof value !== 'string') {
            data[field] = JSON.stringify(value, null, 2)
        }
    }
    return data
}

function encodeJsonFields<T extends Record<string, any>>(data: T): T {
    const payload: Record<string, any> = { ...data }
    for (const field of JSON_FIELDS) {
        const text = typeof payload[field] === 'string' ? payload[field].trim() : ''
        payload[field] = text === '' ? null : JSON.parse(text)
    }
    return payload as T
}

function validateJsonField(_rule: unknown, value: unknown, callback: (error?: Error) => void) {
    const text = typeof value === 'string' ? value.trim() : ''
    if (text === '') {
        callback()
        return
    }
    try {
        const parsed = JSON.parse(text)
        callback(parsed !== null && typeof parsed === 'object' ? undefined : new Error('请输入 JSON 对象或数组'))
    } catch {
        callback(new Error('JSON 格式不正确'))
    }
}

const { form, formRef, submitting, visible, handleSubmit, handleClose, resetForm } =
    useFormDialog<GenCategoryFormData>({
        defaultForm: {
            id: undefined,
            name: '',
            is_featured: false,
            settings: '',
            contact_email: '',
            sort: 0
        },
        modelValue: () => props.modelValue,
        onUpdate: (v) => emit('update:modelValue', v),
        onSuccess: () => emit('success'),
        createFn: (data) => genCategoryApi.create(encodeJsonFields(data)),
        updateFn: (id, data) => genCategoryApi.update(id, encodeJsonFields(data)),
        sourceData: () => decodeJsonFields(props.formData) as Partial<GenCategoryFormData>
    })

const rules = computed<FormRules>(() => ({
    name: [{ required: true, message: t('message.required'), trigger: 'blur' }],
    settings: [{ validator: validateJsonField, trigger: 'blur' }]
}))
</script>
