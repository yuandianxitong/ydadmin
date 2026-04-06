<template>
    <el-dialog
        v-model="visible"
        :title="
            form.id
                ? t('articleCategoryMgmt.editCategory')
                : t('articleCategoryMgmt.addCategory')
        "
        width="520px"
        :close-on-click-modal="false"
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
            <el-form-item :label="t('articleCategoryMgmt.parentCategory')" prop="parent_id">
                <el-tree-select
                    v-model="form.parent_id"
                    :data="parentTreeData"
                    node-key="id"
                    :props="{ label: 'name' }"
                    :placeholder="t('articleCategoryMgmt.parentPlaceholder')"
                    check-strictly
                    clearable
                    style="width: 100%"
                />
            </el-form-item>

            <el-form-item :label="t('articleCategoryMgmt.categoryName')" prop="name">
                <el-input
                    v-model="form.name"
                    :placeholder="t('articleCategoryMgmt.namePlaceholder')"
                    maxlength="50"
                />
            </el-form-item>

            <el-form-item :label="t('articleCategoryMgmt.icon')" prop="icon">
                <el-input
                    v-model="form.icon"
                    :placeholder="t('articleCategoryMgmt.iconPlaceholder')"
                >
                    <template #prepend>
                        <el-icon v-if="form.icon">
                            <component :is="form.icon" />
                        </el-icon>
                    </template>
                </el-input>
            </el-form-item>

            <el-form-item :label="$t('common.sort')" prop="sort">
                <el-input-number
                    v-model="form.sort"
                    :min="0"
                    :max="9999"
                    controls-position="right"
                    style="width: 100%"
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

import { articleCategoryApi } from '@/api/article-category'
import { useFormDialog } from '@/hooks/useFormDialog'

const { t } = useI18n()

interface ArticleCategoryFormData {
    id?: number
    parent_id: number
    name: string
    icon: string
    sort: number
    status: number
}

const props = defineProps<{
    modelValue: boolean
    formData: Record<string, any>
    parentOptions: any[]
}>()

const emit = defineEmits<{
    'update:modelValue': [value: boolean]
    success: []
}>()

const { form, formRef, submitting, visible, handleSubmit, handleClose, resetForm } =
    useFormDialog<ArticleCategoryFormData>({
        defaultForm: {
            id: undefined,
            parent_id: 0,
            name: '',
            icon: '',
            sort: 0,
            status: 1
        },
        modelValue: () => props.modelValue,
        onUpdate: (v) => emit('update:modelValue', v),
        onSuccess: () => emit('success'),
        createFn: (data) => articleCategoryApi.create(data),
        updateFn: (id, data) => articleCategoryApi.update(id, data),
        sourceData: () => props.formData as Partial<ArticleCategoryFormData>
    })

// 构建树形选项
const parentTreeData = computed(() => {
    const topNode = { id: 0, name: t('articleCategoryMgmt.topCategory'), children: [] as any[] }
    return [topNode, ...props.parentOptions]
})

const rules = computed<FormRules>(() => ({
    name: [
        { required: true, message: t('articleCategoryMgmt.validate.nameRequired'), trigger: 'blur' }
    ]
}))
</script>
