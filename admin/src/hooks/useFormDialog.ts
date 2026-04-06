import type { FormInstance } from 'element-plus'
import { ElMessage } from 'element-plus'
import { computed, nextTick, reactive, ref, watch } from 'vue'

import { t } from '@/utils/i18n'

/**
 * 表单弹窗通用 composable
 *
 * 封装所有"新增/编辑弹窗"的通用模式：
 *   - formRef + submitting 状态
 *   - visible 双向绑定（基于 v-model:modelValue）
 *   - open(data?) 打开弹窗并同步数据
 *   - close() 关闭弹窗并清空表单
 *   - handleSubmit 验证 + 判断 create/update + ElMessage 提示 + emit('success')
 *
 * 调用方示例：
 * ```ts
 * const {
 *   visible, form, formRef, submitting,
 *   handleSubmit, handleClose,
 * } = useFormDialog({
 *   defaultForm: { id: undefined, name: '', sort: 0, status: 1 },
 *   modelValue: () => props.modelValue,
 *   onUpdate: (v) => emit('update:modelValue', v),
 *   onSuccess: () => emit('success'),
 *   createFn: (data) => roleApi.createRole(data),
 *   updateFn: (id, data) => roleApi.updateRole(id, data),
 * })
 * ```
 */
export interface UseFormDialogOptions<T extends { id?: number | undefined }> {
    /** 表单初始数据，用于 resetForm */
    defaultForm: T
    /** 读取 modelValue（通常从 defineProps 取） */
    modelValue: () => boolean
    /** 更新 modelValue（通常是 emit('update:modelValue', v)） */
    onUpdate: (value: boolean) => void
    /** 提交成功后触发（通常是 emit('success')） */
    onSuccess?: () => void
    /** 创建接口 */
    createFn: (data: T) => Promise<any>
    /** 更新接口：第一个参数是 id */
    updateFn: (id: number, data: T) => Promise<any>
    /** 成功文案覆盖（可选） */
    successText?: { create?: string; update?: string }
    /** 外部传入的 formData（watch 它变化同步到内部 form） */
    sourceData?: () => Partial<T> | undefined | null
}

export function useFormDialog<T extends { id?: number | undefined }>(
    options: UseFormDialogOptions<T>
) {
    const {
        defaultForm,
        modelValue,
        onUpdate,
        onSuccess,
        createFn,
        updateFn,
        successText = {},
        sourceData
    } = options

    const formRef = ref<FormInstance>()
    const submitting = ref(false)
    const form = reactive({ ...defaultForm }) as T

    /** 弹窗显示状态 —— 代理到父组件 v-model:modelValue */
    const visible = computed({
        get: () => modelValue(),
        set: (v: boolean) => onUpdate(v)
    })

    /** 重置表单为初始值 */
    function resetForm() {
        formRef.value?.resetFields()
        Object.assign(form, defaultForm)
    }

    /** 当 sourceData 变化时同步到内部 form（用于编辑弹窗外部传入数据） */
    if (sourceData) {
        watch(
            () => sourceData(),
            (val) => {
                if (val) {
                    // 先重置再赋值，避免编辑→新增残留数据
                    Object.assign(form, defaultForm)
                    nextTick(() => {
                        Object.assign(form, val)
                    })
                }
            },
            { immediate: true, deep: true }
        )
    }

    /** 关闭弹窗并清空表单 */
    function handleClose() {
        onUpdate(false)
        resetForm()
    }

    /**
     * 提交：验证 → 根据 form.id 判断 create/update → 成功提示 → 关闭 + success
     */
    async function handleSubmit() {
        if (!formRef.value) return
        try {
            await formRef.value.validate()
        } catch {
            return
        }

        submitting.value = true
        try {
            const id = (form as any).id
            if (id) {
                await updateFn(Number(id), { ...form })
                ElMessage.success(successText.update ?? t('message.updateSuccess'))
            } else {
                await createFn({ ...form })
                ElMessage.success(successText.create ?? t('message.createSuccess'))
            }
            onUpdate(false)
            onSuccess?.()
            resetForm()
        } finally {
            submitting.value = false
        }
    }

    return {
        form,
        formRef,
        submitting,
        visible,
        handleSubmit,
        handleClose,
        resetForm
    }
}
