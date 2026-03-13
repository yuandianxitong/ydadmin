<template>
    <div class="export-data">
        <popup
            ref="popupRef"
            :title="$t('exportData.title')"
            width="500px"
            :confirm-button-text="$t('exportData.confirmExport')"
            :async="true"
            @confirm="handleConfirm"
            @open="getData"
        >
            <template #trigger>
                <el-button>{{ $t('exportData.exportBtn') }}</el-button>
            </template>
            <div>
                <el-form ref="formRef" :model="formData" label-width="120px" :rules="formRules">
                    <el-form-item :label="$t('exportData.dataCount')">
                        {{ $t('exportData.dataCountDesc', { count: exportData.count, pages: exportData.sum_page, size: exportData.page_size }) }}
                    </el-form-item>
                    <el-form-item :label="$t('exportData.exportLimit')">
                        {{ $t('exportData.exportLimitDesc', { maxPages: exportData.max_page, maxSize: exportData.all_max_size }) }}
                    </el-form-item>
                    <el-form-item prop="page_type" :label="$t('exportData.exportRange')" required>
                        <el-radio-group v-model="formData.page_type">
                            <el-radio :value="0">{{ $t('exportData.exportAll') }}</el-radio>
                            <el-radio :value="1">{{ $t('exportData.exportByPage') }}</el-radio>
                        </el-radio-group>
                    </el-form-item>
                    <el-form-item v-if="formData.page_type == 1" :label="$t('exportData.pageRange')">
                        <div class="flex">
                            <el-form-item prop="page_start">
                                <el-input
                                    v-model.number="formData.page_start"
                                    style="width: 140px"
                                    placeholder=""
                                ></el-input>
                            </el-form-item>
                            <span class="flex-none ml-2 mr-2">{{ $t('exportData.pageTo') }}</span>
                            <el-form-item prop="page_end">
                                <el-input
                                    v-model.number="formData.page_end"
                                    style="width: 140px"
                                    placeholder=""
                                ></el-input>
                            </el-form-item>
                        </div>
                    </el-form-item>
                    <el-form-item :label="$t('exportData.fileName')" prop="file_name">
                        <el-input
                            v-model="formData.file_name"
                            :placeholder="$t('exportData.fileNamePlaceholder')"
                        ></el-input>
                    </el-form-item>
                </el-form>
            </div>
        </popup>
    </div>
</template>
<script lang="ts" setup>
import type { FormInstance } from 'element-plus'
import { useI18n } from 'vue-i18n'

import Popup from '@/components/Popup/index.vue'
import feedback from '@/utils/feedback'

const { t } = useI18n()

const formRef = shallowRef<FormInstance>()
const props = defineProps({
    params: {
        type: Object,
        default: () => ({})
    },
    pageSize: {
        type: Number,
        default: 25
    },
    fetchFun: {
        type: Function,
        required: true
    }
})
const popupRef = shallowRef<InstanceType<typeof Popup>>()
const formData = reactive({
    page_type: 0,
    page_start: 1,
    page_end: 200,
    file_name: ''
})

const formRules = {
    page_start: [
        { required: true, message: t('exportData.validate.startPageRequired') },
        { type: 'number', message: t('exportData.validate.pageInteger') },
        {
            validator: (rule: any, value: any, callback: any) => {
                if (value <= 0) return callback(new Error(t('exportData.validate.pageMin')))
                callback()
            }
        }
    ],
    page_end: [
        { required: true, message: t('exportData.validate.endPageRequired') },
        { type: 'number', message: t('exportData.validate.pageInteger') },
        {
            validator: (rule: any, value: any, callback: any) => {
                if (value <= 0) return callback(new Error(t('exportData.validate.pageMin')))
                callback()
            }
        }
    ]
}

const exportData = reactive({
    count: 0,
    sum_page: 0,
    page_size: 0,
    max_page: 0,
    all_max_size: 0
})

const getData = async () => {
    const res = await props.fetchFun({
        ...props.params,
        page_size: props.pageSize,
        export: 1
    })
    Object.assign(exportData, res)
    formData.file_name = res.file_name
    formData.page_end = res.page_end
    formData.page_start = res.page_start
}
const handleConfirm = async () => {
    await formRef.value?.validate()
    feedback.loading(t('common.exporting'))
    try {
        await props.fetchFun({
            ...props.params,
            ...formData,
            page_size: props.pageSize,
            export: 2
        })
        popupRef.value?.close()
        feedback.closeLoading()
    } catch (error) {
        feedback.closeLoading()
    }
}
getData()
</script>
