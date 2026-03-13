<template>
    <el-dialog
        :model-value="modelValue"
        :title="formData.id ? $t('department.editDept') : $t('department.addDept')"
        width="560px"
        :close-on-click-modal="false"
        @update:model-value="$emit('update:modelValue', $event)"
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
            <el-form-item :label="$t('department.parentDept')" prop="parent_id">
                <el-tree-select
                    v-model="form.parent_id"
                    :data="parentOptions"
                    :props="{ label: 'name', value: 'id', children: 'children' }"
                    :placeholder="$t('department.parentPlaceholder')"
                    clearable
                    check-strictly
                    :render-after-expand="false"
                    style="width: 100%"
                />
            </el-form-item>

            <el-form-item :label="$t('department.deptName')" prop="name">
                <el-input v-model="form.name" :placeholder="$t('department.namePlaceholder')" maxlength="100" />
            </el-form-item>

            <el-form-item :label="$t('department.deptCode')" prop="code">
                <el-input v-model="form.code" :placeholder="$t('department.codePlaceholder')" maxlength="50" />
            </el-form-item>

            <el-row :gutter="20">
                <el-col :span="12">
                    <el-form-item :label="$t('department.leader')" prop="leader">
                        <el-input v-model="form.leader" :placeholder="$t('department.leaderPlaceholder')" maxlength="50" />
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="$t('department.phone')" prop="phone">
                        <el-input
                            v-model="form.phone"
                            :placeholder="$t('department.phonePlaceholder')"
                            maxlength="20"
                        />
                    </el-form-item>
                </el-col>
            </el-row>

            <el-form-item :label="$t('admin.email')" prop="email">
                <el-input v-model="form.email" :placeholder="$t('department.emailPlaceholder')" maxlength="100" />
            </el-form-item>

            <el-row :gutter="20">
                <el-col :span="12">
                    <el-form-item :label="$t('common.sort')" prop="sort">
                        <el-input-number
                            v-model="form.sort"
                            :min="0"
                            :max="9999"
                            style="width: 100%"
                        />
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="$t('common.status')" prop="status">
                        <el-radio-group v-model="form.status">
                            <el-radio :value="1">{{ $t('common.enable') }}</el-radio>
                            <el-radio :value="0">{{ $t('common.disable') }}</el-radio>
                        </el-radio-group>
                    </el-form-item>
                </el-col>
            </el-row>

            <el-form-item :label="$t('common.remark')" prop="remark">
                <el-input
                    v-model="form.remark"
                    type="textarea"
                    :rows="3"
                    :placeholder="$t('common.remark')"
                    maxlength="255"
                    show-word-limit
                />
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

import { departmentApi } from '@/api/department'

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
    parent_id: 0,
    name: '',
    code: '',
    leader: '',
    phone: '',
    email: '',
    status: 1,
    sort: 0,
    remark: ''
})

const rules = computed<FormRules>(() => ({
    name: [{ required: true, message: t('department.validate.nameRequired'), trigger: 'blur' }],
    email: [{ type: 'email', message: t('admin.validate.emailFormat'), trigger: 'blur' }]
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
                leader: props.formData.leader || '',
                phone: props.formData.phone || '',
                email: props.formData.email || '',
                status: props.formData.status ?? 1,
                sort: props.formData.sort ?? 0,
                remark: props.formData.remark || ''
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
        leader: '',
        phone: '',
        email: '',
        status: 1,
        sort: 0,
        remark: ''
    })
}

const handleSubmit = async () => {
    if (!formRef.value) return
    await formRef.value.validate()

    submitting.value = true
    try {
        if (form.id) {
            await departmentApi.update(form.id, { ...form })
            ElMessage.success(t('message.updateSuccess'))
        } else {
            await departmentApi.create({ ...form })
            ElMessage.success(t('message.createSuccess'))
        }
        emit('update:modelValue', false)
        emit('success')
    } catch (error: any) {
        ElMessage.error(error?.message || t('message.fetchFailed'))
    } finally {
        submitting.value = false
    }
}
</script>
