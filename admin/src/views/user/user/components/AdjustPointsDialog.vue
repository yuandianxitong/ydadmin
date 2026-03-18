<template>
    <el-dialog
        v-model="visible"
        title="调整积分"
        width="480px"
        :close-on-click-modal="false"
        @close="handleClose"
    >
        <div class="current-info">
            <p>当前积分：<strong>{{ currentPoints }}</strong></p>
        </div>

        <el-form ref="formRef" :model="form" :rules="rules" label-width="80px">
            <el-form-item label="调整积分" prop="points">
                <el-input-number
                    v-model="form.points"
                    :step="1"
                    :precision="0"
                    placeholder="正数增加，负数减少"
                    style="width: 100%"
                    controls-position="right"
                />
            </el-form-item>

            <el-form-item label="备注" prop="remark">
                <el-input
                    v-model="form.remark"
                    type="textarea"
                    :rows="3"
                    placeholder="请输入调整原因"
                />
            </el-form-item>
        </el-form>

        <template #footer>
            <span class="dialog-footer">
                <el-button @click="handleClose">取消</el-button>
                <el-button type="primary" :loading="submitLoading" @click="handleSubmit">
                    确定
                </el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script setup lang="ts" name="AdjustPointsDialog">
import { ElForm, ElMessage } from 'element-plus'
import { computed, reactive, ref } from 'vue'

import { userManageApi } from '@/api/user'

interface Props {
    modelValue: boolean
    userId: number
    currentPoints: number
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
    points: 0,
    remark: ''
})

// 表单验证规则
const rules = {
    points: [
        { required: true, message: '请输入调整积分', trigger: 'blur' },
        {
            validator: (_rule: any, value: number, callback: (error?: Error) => void) => {
                if (value === 0) {
                    callback(new Error('调整积分不能为0'))
                } else {
                    callback()
                }
            },
            trigger: 'blur'
        }
    ],
    remark: [{ required: true, message: '请输入备注', trigger: 'blur' }]
}

// 提交加载状态
const submitLoading = ref(false)

// 提交表单
const handleSubmit = async () => {
    if (!formRef.value) return

    try {
        await formRef.value.validate()

        submitLoading.value = true

        await userManageApi.adjustPoints({
            user_id: props.userId,
            points: form.points,
            remark: form.remark
        })

        ElMessage.success('积分调整成功')
        emit('success')
        handleClose()
    } catch (error) {
        console.error('积分调整失败:', error)
    } finally {
        submitLoading.value = false
    }
}

// 关闭弹窗
const handleClose = () => {
    formRef.value?.resetFields()
    Object.assign(form, {
        points: 0,
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
            color: var(--el-color-primary);
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
