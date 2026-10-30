<template>
    <div class="import-data">
        <Popup
            ref="popupRef"
            :title="$t('importData.title')"
            width="820px"
            custom-class="pro-import-dialog"
            :confirm-button-text="confirmText"
            :cancel-button-text="cancelText"
            :confirm-loading="importing"
            :async="true"
            @confirm="handleConfirm"
            @open="handleOpen"
        >
            <template #trigger
                ><el-button
                    ><i class="i-svg:upload" />{{ $t('importData.importBtn') }}</el-button
                ></template
            >
            <div class="pro-import">
                <div class="imp-steps">
                    <div
                        v-for="(label, index) in [
                            $t('proPage.importChoose'),
                            $t('proPage.importProcess'),
                            $t('proPage.importResult')
                        ]"
                        :key="index"
                        class="imp-step"
                        :class="{
                            on: (result ? 2 : importing ? 1 : 0) === index,
                            done: (result ? 2 : importing ? 1 : 0) > index
                        }"
                    >
                        <span class="imp-step-n"
                            ><i v-if="result && index < 2" class="i-svg:check" /><template v-else>{{
                                index + 1
                            }}</template></span
                        ><span>{{ label }}</span>
                    </div>
                </div>
                <template v-if="!result">
                    <div v-if="templateUrl" class="imp-tpl">
                        <div class="imp-tpl-ic"><i class="i-svg:file-text" /></div>
                        <div class="imp-tpl-txt">
                            <b>{{ $t('proPage.importTemplate') }}</b
                            ><span>{{ $t('proPage.importTemplateHint') }}</span>
                        </div>
                        <el-button type="primary" @click="handleDownloadTemplate"
                            ><i class="i-svg:download" />{{ $t('proPage.downloadCsv') }}</el-button
                        >
                    </div>
                    <el-upload
                        ref="uploadRef"
                        class="imp-upload"
                        drag
                        :auto-upload="false"
                        :limit="1"
                        accept=".csv"
                        :on-change="handleFileChange"
                        :on-exceed="handleExceed"
                        :disabled="importing"
                    >
                        <div class="imp-drop-ic"><i class="i-svg:upload" /></div>
                        <div class="imp-drop-title">
                            {{ $t('importData.dragText') }}
                            <em>{{ $t('importData.clickText') }}</em>
                        </div>
                        <div class="imp-drop-desc">{{ $t('proPage.csvOnly') }}</div>
                    </el-upload>
                    <div v-if="selectedFile" class="imp-file-info">
                        <i class="i-svg:file-text" /><b>{{ selectedFile.name }}</b
                        ><span>{{ (selectedFile.size / 1024).toFixed(1) }} KB</span>
                    </div>
                    <div v-if="importing" class="imp-working">
                        <i class="i-svg:loader is-loading" />{{ $t('importData.importing') }}
                    </div>
                </template>
                <div v-else class="imp-done">
                    <div class="imp-done-icon" :class="{ warning: result.fail > 0 }">
                        <i :class="result.fail > 0 ? 'i-svg:triangle-alert' : 'i-svg:check'" />
                    </div>
                    <h3>{{ $t('importData.importComplete') }}</h3>
                    <div class="imp-done-stats">
                        <div>
                            <b>{{ result.success }}</b
                            ><span>{{ $t('proPage.importSuccess') }}</span>
                        </div>
                        <div :class="{ failed: result.fail > 0 }">
                            <b>{{ result.fail }}</b
                            ><span>{{ $t('proPage.importFailure') }}</span>
                        </div>
                    </div>
                    <div v-if="result.errors?.length" class="imp-errors">
                        <div class="imp-errors-head">{{ $t('importData.errorList') }}</div>
                        <el-scrollbar max-height="200px"
                            ><div
                                v-for="(error, index) in result.errors"
                                :key="index"
                                class="imp-error-row"
                            >
                                {{ error }}
                            </div></el-scrollbar
                        >
                    </div>
                </div>
            </div>
        </Popup>
    </div>
</template>
<script lang="ts" setup>
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
        if (props.templateUrl.startsWith('data:')) {
            const link = document.createElement('a')
            link.href = props.templateUrl
            link.download = 'import-template.csv'
            link.click()
        } else {
            window.open(props.templateUrl, '_blank', 'noopener')
        }
    }
}

const handleConfirm = async () => {
    if (importing.value) return
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
.imp-steps {
    display: flex;
    gap: 24px;
    justify-content: center;
    padding: 4px 0 24px;
    border-bottom: 1px solid var(--ink-100);
    margin-bottom: 24px;
}
.imp-step {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--ink-400);
    font-size: 12.5px;
}
.imp-step.on {
    color: var(--brand-500);
}
.imp-step.done {
    color: var(--success);
}
.imp-step-n {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: var(--ink-100);
    font-weight: 600;
}
.imp-step.on .imp-step-n {
    color: white;
    background: var(--brand-500);
}
.imp-step.done .imp-step-n {
    background: var(--success-50);
}
.imp-tpl {
    display: flex;
    align-items: center;
    gap: 14px;
    background: var(--brand-50);
    border: 1px solid var(--brand-100);
    padding: 16px;
    border-radius: var(--r-md);
    margin-bottom: 20px;
}
.imp-tpl-ic {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    flex: none;
    border-radius: 8px;
    background: var(--card-bg);
    color: var(--brand-500);
    font-size: 22px;
}
.imp-tpl-txt {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.imp-tpl-txt b {
    font-size: 13px;
    font-weight: 500;
    color: var(--ink-800);
}
.imp-tpl-txt span {
    font-size: 12px;
    color: var(--ink-500);
}
.imp-upload :deep(.el-upload),
.imp-upload :deep(.el-upload-dragger) {
    width: 100%;
}
.imp-upload :deep(.el-upload-dragger) {
    padding: 32px 16px;
    border-radius: var(--r-md);
    border-color: var(--ink-200);
    background: var(--card-bg);
}
.imp-upload :deep(.el-upload-dragger:hover) {
    border-color: var(--brand-500);
    background: var(--brand-50);
}
.imp-drop-ic {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: var(--brand-50);
    color: var(--brand-500);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    margin: 0 auto 14px;
}
.imp-drop-title {
    font-size: 13px;
    color: var(--ink-700);
}
.imp-drop-title em {
    color: var(--brand-500);
    font-style: normal;
}
.imp-drop-desc {
    font-size: 12px;
    color: var(--ink-400);
    margin-top: 8px;
}
.imp-file-info {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--ink-50);
    padding: 12px;
    border-radius: 6px;
    margin-top: 12px;
    font-size: 12px;
    color: var(--ink-500);
}
.imp-file-info b {
    font-weight: 500;
    color: var(--ink-700);
    overflow-wrap: anywhere;
}
.imp-file-info span {
    margin-left: auto;
    white-space: nowrap;
}
.imp-working {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 20px;
    color: var(--brand-500);
}
.imp-done {
    text-align: center;
}
.imp-done-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    width: 66px;
    height: 66px;
    border-radius: 50%;
    font-size: 30px;
    color: var(--success);
    background: var(--success-50);
}
.imp-done-icon.warning {
    color: var(--warning);
    background: var(--amber-50);
}
.imp-done h3 {
    color: var(--ink-900);
    font-size: 18px;
    margin: 14px 0 20px;
}
.imp-done-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    background: var(--ink-50);
    border: 1px solid var(--ink-100);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}
.imp-done-stats div {
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.imp-done-stats b {
    font-family: var(--font-num);
    font-size: 28px;
    color: var(--success);
}
.imp-done-stats .failed b {
    color: var(--danger);
}
.imp-done-stats span {
    font-size: 12px;
    color: var(--ink-500);
}
.imp-errors {
    border: 1px solid var(--ink-100);
    border-radius: 6px;
    text-align: left;
    overflow: hidden;
}
.imp-errors-head {
    padding: 10px 12px;
    background: var(--ink-50);
    font-size: 12.5px;
    font-weight: 500;
}
.imp-error-row {
    padding: 8px 12px;
    color: var(--danger);
    font-size: 12px;
    border-top: 1px solid var(--ink-100);
}
@media (max-width: 640px) {
    .imp-steps {
        gap: 12px;
    }
    .imp-step {
        gap: 5px;
        font-size: 11px;
    }
    .imp-step-n {
        width: 22px;
        height: 22px;
        flex: none;
    }
    .imp-tpl {
        flex-wrap: wrap;
    }
    .imp-tpl .el-button {
        width: 100%;
    }
    .imp-file-info {
        flex-wrap: wrap;
    }
}
</style>
