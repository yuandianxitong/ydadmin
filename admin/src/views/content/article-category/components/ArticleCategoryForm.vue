<template>
    <el-dialog
        :model-value="modelValue"
        :title="formData.id ? '编辑分类' : '新增分类'"
        width="520px"
        :close-on-click-modal="false"
        @update:model-value="$emit('update:modelValue', $event)"
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
            <el-form-item label="上级分类" prop="parent_id">
                <el-tree-select
                    v-model="form.parent_id"
                    :data="parentTreeData"
                    node-key="id"
                    :props="{ label: 'name' }"
                    placeholder="请选择上级分类"
                    check-strictly
                    clearable
                    style="width: 100%"
                />
            </el-form-item>

            <el-form-item label="分类名称" prop="name">
                <el-input v-model="form.name" placeholder="请输入分类名称" maxlength="50" />
            </el-form-item>

            <el-form-item label="图标" prop="icon">
                <el-input v-model="form.icon" placeholder="请输入图标名称">
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

import { articleCategoryApi } from '@/api/article-category'

const { t } = useI18n()

const props = defineProps<{
    modelValue: boolean
    formData: Record<string, any>
    parentOptions: any[]
}>()

const emit = defineEmits<{
    'update:modelValue': [value: boolean]
    success: []
}>()

const formRef = ref<FormInstance>()
const submitting = ref(false)

const form = reactive({
    id: undefined as number | undefined,
    parent_id: 0 as number,
    name: '',
    icon: '',
    sort: 0,
    status: 1
})

// 构建树形选项
const parentTreeData = computed(() => {
    const topNode = { id: 0, name: '顶级分类', children: [] as any[] }
    return [topNode, ...props.parentOptions]
})

const rules = computed<FormRules>(() => ({
    name: [{ required: true, message: '请输入分类名称', trigger: 'blur' }]
}))

watch(
    () => props.modelValue,
    (val) => {
        if (val && props.formData) {
            Object.assign(form, {
                id: props.formData.id || undefined,
                parent_id: props.formData.parent_id ?? 0,
                name: props.formData.name || '',
                icon: props.formData.icon || '',
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
        icon: '',
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
            await articleCategoryApi.update(form.id, { ...form })
            ElMessage.success(t('message.updateSuccess'))
        } else {
            await articleCategoryApi.create({ ...form })
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
