<template>
    <el-dialog
        v-model="visible"
        :title="t('userMgmt.adjustBalanceDialog.title')"
        width="480px"
        :close-on-click-modal="false"
        @close="handleClose"
    >
        <div class="current-info">
            <p>{{ t('userMgmt.adjustBalanceDialog.currentBalance') }}：<strong>¥{{ currentBalance }}</strong></p>
        </div>

        <el-form ref="formRef" :model="form" :rules="rules" label-width="80px">
            <el-form-item :label="t('userMgmt.adjustBalanceDialog.amount')" prop="amount">
                <el-input-number
                    v-model="form.amount"
                    :step="0.01"
                    :precision="2"
                    :placeholder="t('userMgmt.adjustBalanceDialog.amountPlaceholder')"
                    style="width: 100%"
                    controls-position="right"
                />
            </el-form-item>

            <el-form-item :label="t('userMgmt.adjustBalanceDialog.remark')" prop="remark">
                <el-input
                    v-model="form.remark"
                    type="textarea"
                    :rows="3"
                    :placeholder="t('userMgmt.adjustBalanceDialog.remarkPlaceholder')"
                />
            </el-form-item>
        </el-form>

        <template #footer>
            <span class="dialog-footer">
                <el-button @click="handleClose">{{ t('userMgmt.adjustBalanceDialog.cancel') }}</el-button>
                <el-button type="primary" :loading="submitLoading" @click="handleSubmit">
                    {{ t('userMgmt.adjustBalanceDialog.confirm') }}
                </el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script setup lang="ts" name="AdjustBalanceDialog">
import { ElForm, ElMessage } from 'element-plus'
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { userManageApi } from '@/api/user'

const { t } = useI18n()

interface Props {
    modelValue: boolean
    userId: number
    currentBalance: string
}

interface Emits {
    (e: 'update:modelValue', value: boolean): void
    (e: 'success'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

// 表单引用
const formRef = ref<InstanceType<typeof ElForm>>()

// 弹窗显示状态
const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
})

// 表单数据
const form = reactive({
    amount: 0,
    remark: ''
})

// 表单验证规则
const rules = {
    amount: [
        { required: true, message: () => t('userMgmt.adjustBalanceDialog.validate.amountRequired'), trigger: 'blur' },
        {
            validator: (_rule: any, value: number, callback: (error?: Error) => void) => {
                if (value === 0) {
                    callback(new Error(t('userMgmt.adjustBalanceDialog.validate.amountNotZero')))
                } else {
                    callback()
                }
            },
            trigger: 'blur'
        }
    ],
    remark: [{ required: true, message: () => t('userMgmt.adjustBalanceDialog.validate.remarkRequired'), trigger: 'blur' }]
}

// 提交加载状态
const submitLoading = ref(false)

// 提交表单
const handleSubmit = async () => {
    if (!formRef.value) return

    try {
        await formRef.value.validate()

        submitLoading.value = true

        await userManageApi.adjustBalance({
            user_id: props.userId,
            amount: form.amount,
            remark: form.remark
        })

        ElMessage.success(t('userMgmt.adjustBalanceDialog.successMsg'))
        emit('success')
        handleClose()
    } catch (error) {
        console.error('余额调整失败:', error)
    } finally {
        submitLoading.value = false
    }
}

// 关闭弹窗
const handleClose = () => {
    formRef.value?.resetFields()
    Object.assign(form, {
        amount: 0,
        remark: ''
    })
    visible.value = false
}
</script>

<style lang="scss" scoped>
.current-info {
    background-color: var(--el-fill-color-light);
    border-radius: 6px;
    padding: 16px;
    margin-bottom: 20px;

    p {
        margin: 0;
        color: var(--el-text-color-regular);

        strong {
            color: var(--el-color-warning);
            font-size: 16px;
        }
    }
}

.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
</style>
