<template>
    <el-dialog
        v-model="visible"
        :title="
            form.id
                ? $t('notificationMgmt.editNotification')
                : $t('notificationMgmt.addNotification')
        "
        width="640px"
        :close-on-click-modal="false"
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
            <el-form-item :label="$t('notificationMgmt.notificationTitle')" prop="title">
                <el-input
                    v-model="form.title"
                    :placeholder="$t('notificationMgmt.titlePlaceholder')"
                    maxlength="200"
                    show-word-limit
                />
            </el-form-item>

            <el-row :gutter="20">
                <el-col :span="12">
                    <el-form-item :label="$t('notificationMgmt.notificationType')" prop="type">
                        <el-select
                            v-model="form.type"
                            :placeholder="$t('notificationMgmt.typePlaceholder')"
                            style="width: 100%"
                        >
                            <el-option
                                :label="$t('notificationMgmt.typeOptions.system')"
                                :value="1"
                            />
                            <el-option
                                :label="$t('notificationMgmt.typeOptions.todo')"
                                :value="2"
                            />
                            <el-option
                                :label="$t('notificationMgmt.typeOptions.business')"
                                :value="3"
                            />
                        </el-select>
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="$t('notificationMgmt.targetScope')" prop="target_type">
                        <!-- 后端不允许修改已有通知的 target_type（422），编辑时锁定 -->
                        <el-select
                            v-model="form.target_type"
                            :placeholder="$t('common.selectPlaceholder')"
                            :disabled="!!form.id"
                            style="width: 100%"
                        >
                            <el-option
                                :label="$t('notificationMgmt.scopeOptions.all')"
                                :value="TARGET_ALL"
                            />
                            <el-option
                                :label="$t('notificationMgmt.scopeOptions.specified')"
                                :value="TARGET_ADMINS"
                            />
                        </el-select>
                    </el-form-item>
                </el-col>
            </el-row>

            <el-form-item
                v-if="form.target_type === TARGET_ADMINS"
                :label="$t('notificationMgmt.recipients')"
                prop="admin_ids"
            >
                <el-select
                    v-model="form.admin_ids"
                    multiple
                    filterable
                    remote
                    reserve-keyword
                    :remote-method="searchRecipients"
                    :loading="optionsLoading"
                    :placeholder="$t('notificationMgmt.recipientsPlaceholder')"
                    style="width: 100%"
                >
                    <el-option
                        v-for="option in recipientOptions"
                        :key="option.id"
                        :label="recipientLabel(option)"
                        :value="option.id"
                    />
                </el-select>
            </el-form-item>

            <el-form-item :label="$t('notificationMgmt.content')" prop="content">
                <el-input
                    v-model="form.content"
                    type="textarea"
                    :rows="6"
                    :placeholder="$t('notificationMgmt.contentPlaceholder')"
                />
            </el-form-item>

            <el-form-item :label="$t('common.status')" prop="status">
                <el-radio-group v-model="form.status">
                    <el-radio :value="1">{{ $t('notificationMgmt.publish') }}</el-radio>
                    <el-radio :value="0">{{ $t('notificationMgmt.draft') }}</el-radio>
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
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { notificationApi } from '@/api/notification'
import { useFormDialog } from '@/hooks/useFormDialog'

import {
    buildNotificationPayload,
    mergeRecipientOptions,
    recipientLabel,
    type RecipientOption,
    TARGET_ADMINS,
    TARGET_ALL
} from '../helpers'

const { t } = useI18n()

interface NotificationForm {
    id?: number
    title: string
    content: string
    type: number
    target_type: number
    admin_ids: number[]
    status: number
}

const props = defineProps<{
    modelValue: boolean
    formData: Record<string, any>
}>()

const emit = defineEmits<{
    'update:modelValue': [value: boolean]
    success: []
}>()

// 编辑时详情把当前全部收件人（可能含超出编辑者数据范围 / 已删除的管理员）回填进表单；
// 原样提交会被后端按越权 / 不存在 id 判 422。这里记住打开表单那一刻加载的收件人集合，
// 提交时只有真的改动过才带上 admin_ids（见 helpers.buildNotificationPayload）。
// create 场景没有「加载时的集合」，保持 undefined，让 buildNotificationPayload 总是带上。
const initialAdminIds = ref<number[] | undefined>(undefined)
watch(
    () => props.formData,
    (val) => {
        initialAdminIds.value = val?.id
            ? [...((val.admin_ids as number[] | undefined) ?? [])]
            : undefined
    },
    { immediate: true, deep: true }
)

const { form, formRef, submitting, visible, handleSubmit, handleClose, resetForm } =
    useFormDialog<NotificationForm>({
        defaultForm: {
            id: undefined,
            title: '',
            content: '',
            type: 1,
            target_type: TARGET_ALL,
            admin_ids: [],
            status: 1
        },
        modelValue: () => props.modelValue,
        onUpdate: (v) => emit('update:modelValue', v),
        onSuccess: () => emit('success'),
        createFn: (data) => notificationApi.create(buildNotificationPayload(data)),
        updateFn: (id, data) =>
            notificationApi.update(id, buildNotificationPayload(data, initialAdminIds.value)),
        sourceData: () => props.formData as Partial<NotificationForm>
    })

const recipientOptions = ref<RecipientOption[]>([])
const optionsLoading = ref(false)

/** 远程搜索候选人，并把已选 id 合并进选项（见 helpers.mergeRecipientOptions） */
const searchRecipients = async (keyword: string) => {
    optionsLoading.value = true
    try {
        const res = await notificationApi.adminOptions(keyword.trim())
        recipientOptions.value = mergeRecipientOptions(
            recipientOptions.value,
            res.data ?? [],
            form.admin_ids
        )
    } catch {
        // request.ts 响应拦截器已提示错误；保留已选项的显示
        recipientOptions.value = mergeRecipientOptions(recipientOptions.value, [], form.admin_ids)
    } finally {
        optionsLoading.value = false
    }
}

// 打开弹窗或切到「指定管理员」时加载一次候选人。
// sourceData 在 nextTick 里才把编辑数据（含 admin_ids）写进 form，所以先等一拍再合并。
watch(
    () => [visible.value, form.target_type] as const,
    async ([opened, targetType]) => {
        if (!opened || targetType !== TARGET_ADMINS) return
        await nextTick()
        await searchRecipients('')
    }
)

const rules = computed<FormRules>(() => ({
    title: [
        { required: true, message: t('notificationMgmt.validate.titleRequired'), trigger: 'blur' }
    ],
    content: [
        { required: true, message: t('notificationMgmt.validate.contentRequired'), trigger: 'blur' }
    ],
    type: [
        { required: true, message: t('notificationMgmt.validate.typeRequired'), trigger: 'change' }
    ],
    admin_ids: [
        {
            validator: (_rule, value: number[] | undefined, callback) => {
                if (form.target_type === TARGET_ADMINS && (!value || value.length === 0)) {
                    callback(new Error(t('notificationMgmt.validate.recipientsRequired')))
                    return
                }
                callback()
            },
            trigger: 'change'
        }
    ]
}))
</script>
