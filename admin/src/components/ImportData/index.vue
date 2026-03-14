<template>
    <div class="import-data">
        <popup
            ref="popupRef"
            :title="$t('importData.title')"
            width="500px"
            :confirm-button-text="confirmText"
            :cancel-button-text="cancelText"
            :async="true"
            @confirm="handleConfirm"
            @open="handleOpen"
        >
            <template #trigger>
                <el-button>{{ $t('importData.importBtn') }}</el-button>
            </template>
            <div>
                <!-- Template download -->
                <div v-if="templateUrl" class="mb-4">
                    <el-button type="primary" link @click="handleDownloadTemplate">
                        <el-icon class="mr-1"><Download /></el-icon>
                        {{ $t('importData.downloadTemplate') }}
                    </el-button>
                </div>

                <!-- Upload area (shown before import) -->
                <el-upload
                    v-if="!result"
                    ref="uploadRef"
                    drag
                    :auto-upload="false"
                    :limit="1"
                    accept=".xlsx,.xls,.csv"
                    :on-change="handleFileChange"
                    :on-exceed="handleExceed"
                >
                    <el-icon class="el-icon--upload"><UploadFilled /></el-icon>
                    <div class="el-upload__text">
                        {{ $t('importData.dragText') }}
                        <em>{{ $t('importData.clickText') }}</em>
                    </div>
                    <template #tip>
                        <div class="el-upload__tip">
                            {{ $t('importData.fileTip') }}
                        </div>
                    </template>
                </el-upload>

                <!-- Loading state -->
                <div v-if="importing" class="text-center py-6">
                    <el-icon class="is-loading" :size="32"><Loading /></el-icon>
                    <div class="mt-2">{{ $t('importData.importing') }}</div>
                </div>

                <!-- Result display -->
                <div v-if="result" class="import-result">
                    <div class="import-result__summary">
                        <el-alert
                            :title="$t('importData.importComplete')"
                            :type="result.fail > 0 ? 'warning' : 'success'"
                            :closable="false"
                            show-icon
                        >
                            <template #default>
                                <div>
                                    {{ $t('importData.successCount', { count: result.success }) }}
                                    <span v-if="result.fail > 0" class="ml-4 text-red-500">
                                        {{ $t('importData.failCount', { count: result.fail }) }}
                                    </span>
                                </div>
                            </template>
                        </el-alert>
                    </div>
                    <!-- Error list -->
                    <div v-if="result.errors && result.errors.length > 0" class="import-result__errors mt-4">
                        <div class="text-sm font-bold mb-2">{{ $t('importData.errorList') }}</div>
                        <el-scrollbar max-height="200px">
                            <div
                                v-for="(error, index) in result.errors"
                                :key="index"
                                class="import-result__error-item"
                            >
                                {{ error }}
                            </div>
                        </el-scrollbar>
                    </div>
                </div>
            </div>
        </popup>
    </div>
</template>
<script lang="ts" setup>
import { Download, Loading, UploadFilled } from '@element-plus/icons-vue'
import type { UploadFile, UploadInstance } from 'element-plus'
import { useI18n } from 'vue-i18n'

import Popup from '@/components/Popup/index.vue'
import feedback from '@/utils/feedback'

interface ImportResult {
    success: number
    fail: number
    errors?: string[]
}

const { t } = useI18n()

const props = defineProps<{
    importFun: (file: File) => Promise<ImportResult>
    templateUrl?: string
}>()

const emit = defineEmits<{
    success: []
}>()

const popupRef = shallowRef<InstanceType<typeof Popup>>()
const uploadRef = shallowRef<UploadInstance>()
const selectedFile = shallowRef<File | null>(null)
const importing = ref(false)
const result = ref<ImportResult | null>(null)

const confirmText = computed<string | boolean>(() => {
    if (result.value) return false
    return t('importData.confirmImport')
})

const cancelText = computed<string | boolean>(() => {
    if (result.value) return t('common.close')
    return t('common.cancel')
})

const handleOpen = () => {
    selectedFile.value = null
    importing.value = false
    result.value = null
    nextTick(() => {
        uploadRef.value?.clearFiles()
    })
}

const handleFileChange = (file: UploadFile) => {
    selectedFile.value = file.raw ?? null
}

const handleExceed = () => {
    feedback.msgWarning(t('importData.exceedLimit'))
}

const handleDownloadTemplate = () => {
    if (props.templateUrl) {
        window.open(props.templateUrl, '_blank')
    }
}

const handleConfirm = async () => {
    if (!selectedFile.value) {
        feedback.msgWarning(t('importData.selectFile'))
        return
    }
    importing.value = true
    try {
        const res = await props.importFun(selectedFile.value)
        result.value = res
        if (res.success > 0) {
            emit('success')
        }
    } catch (error: any) {
        feedback.msgError(error.message || t('importData.importFailed'))
    } finally {
        importing.value = false
    }
}
</script>
<style scoped lang="scss">
.import-result {
    &__error-item {
        padding: 6px 12px;
        font-size: 13px;
        color: var(--el-color-danger);
        line-height: 1.5;
        border-bottom: 1px solid var(--el-border-color-lighter);

        &:last-child {
            border-bottom: none;
        }
    }
}
</style>
