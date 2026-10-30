<template>
    <div class="generator-page">
        <div class="page-head">
            <div>
                <div class="page-title">{{ $t('generator.title') }}</div>
                <div class="page-desc">{{ $t('proPage.system_generator_index') }}</div>
            </div>
            <div class="page-actions">
                <el-button @click="fetchTables"
                    ><i class="i-svg:refresh-cw" />{{ $t('proPage.syncTables') }}</el-button
                >
            </div>
        </div>

        <!-- Step 1: 选择数据表 -->
        <ProTable
            v-if="step === 0"
            :title="$t('proPage.databaseTables')"
            storage-key="generator-tables"
            row-key="name"
            :columns="[
                {
                    key: 'name',
                    label: $t('proPage.table_system_generator_index_vue_name'),
                    minWidth: 180
                },
                {
                    key: 'comment',
                    label: $t('proPage.table_system_generator_index_vue_comment'),
                    minWidth: 160,
                    showOverflowTooltip: true
                },
                {
                    key: 'entity',
                    label: $t('proPage.table_system_generator_index_vue_entity'),
                    minWidth: 140
                },
                {
                    key: 'rows',
                    label: $t('proPage.table_system_generator_index_vue_rows'),
                    width: 100
                },
                { key: 'engine', label: $t('generator.engine'), width: 100, defaultVisible: false },
                {
                    key: 'action',
                    label: $t('proPage.table_system_generator_index_vue_action'),
                    width: 220,
                    fixed: 'right',
                    required: true
                }
            ]"
            :data="pagedTables"
            :loading="tableLoading"
            :pagination="{ page: tablePage, limit: tableLimit, total: filteredTables.length }"
            @page-change="tablePage = $event"
            @size-change="handleTableSizeChange"
        >
            <template #headerExtra
                ><el-input
                    v-model="tableSearch"
                    :placeholder="$t('generator.searchTable')"
                    clearable
                    size="small"
                    style="width: 200px"
                    @input="tablePage = 1"
                    ><template #prefix><i class="i-svg:search" /></template></el-input
            ></template>
            <template #name="{ row }"
                ><code class="tbl-name">{{ row.name }}</code></template
            >
            <template #entity="{ row }"
                ><code class="tbl-entity">{{ tableEntityName(row.name) }}</code></template
            >
            <template #action="{ row }"
                ><el-button type="primary" size="small" text @click="configureTable(row)">{{
                    $t('proPage.configureGenerator')
                }}</el-button></template
            >
        </ProTable>

        <el-dialog
            :model-value="step > 0"
            :title="
                step === 1
                    ? $t('generator.basicConfig') + ' · ' + selectedTable
                    : $t('generator.previewTitle')
            "
            width="1000px"
            :close-on-click-modal="false"
            @close="step = 0"
            class="pro-generator-dialog"
        >
            <!-- Step 2: 配置 -->
            <el-steps :active="step" finish-status="success" class="mb-6">
                <el-step :title="$t('generator.step1')" />
                <el-step :title="$t('generator.step2')" />
                <el-step :title="$t('generator.step3')" />
            </el-steps>
            <el-card v-if="step === 1" shadow="never">
                <template #header>{{ $t('generator.basicConfig') }} — {{ selectedTable }}</template>
                <el-form label-width="120px" class="mb-6">
                    <el-row :gutter="24">
                        <el-col :span="8">
                            <el-form-item :label="$t('generator.moduleName')">
                                <el-input
                                    v-model="config.module_name"
                                    :placeholder="$t('generator.moduleNamePlaceholder')"
                                />
                            </el-form-item>
                        </el-col>
                        <el-col :span="8">
                            <el-form-item :label="$t('generator.modelName')">
                                <el-input
                                    v-model="config.model_name"
                                    :placeholder="$t('generator.modelNamePlaceholder')"
                                />
                            </el-form-item>
                        </el-col>
                        <el-col :span="8">
                            <el-form-item :label="$t('generator.cnName')">
                                <el-input
                                    v-model="config.table_comment"
                                    :placeholder="$t('generator.cnNamePlaceholder')"
                                />
                            </el-form-item>
                        </el-col>
                    </el-row>
                </el-form>

                <h4 class="mb-3">{{ $t('generator.fieldConfig') }}</h4>
                <el-table :data="columns" border size="small" max-height="400">
                    <el-table-column prop="name" :label="$t('generator.fieldName')" width="140" />
                    <el-table-column prop="comment" :label="$t('generator.comment')" width="120" />
                    <el-table-column
                        prop="raw_type"
                        :label="$t('generator.fieldType')"
                        width="130"
                    />
                    <el-table-column :label="$t('generator.formType')" width="130">
                        <template #default="{ row }">
                            <el-select
                                v-model="row.form_type"
                                size="small"
                                :disabled="!row.in_form"
                            >
                                <el-option
                                    :label="$t('generator.formTypeOptions.input')"
                                    value="input"
                                />
                                <el-option
                                    :label="$t('generator.formTypeOptions.textarea')"
                                    value="textarea"
                                />
                                <el-option
                                    :label="$t('generator.formTypeOptions.number')"
                                    value="number"
                                />
                                <el-option
                                    :label="$t('generator.formTypeOptions.switch')"
                                    value="switch"
                                />
                                <el-option
                                    :label="$t('generator.formTypeOptions.select')"
                                    value="select"
                                />
                                <el-option
                                    :label="$t('generator.formTypeOptions.date')"
                                    value="datepicker"
                                />
                                <el-option
                                    :label="$t('generator.formTypeOptions.image')"
                                    value="image"
                                />
                            </el-select>
                        </template>
                    </el-table-column>
                    <el-table-column label="下拉选项" min-width="190">
                        <template #default="{ row }">
                            <el-input
                                v-if="row.form_type === 'select'"
                                v-model="row.options_text"
                                type="textarea"
                                :rows="3"
                                placeholder="每行：值=标签，例如 1=分类一"
                            />
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('generator.listShow')" width="80" align="center">
                        <template #default="{ row }">
                            <el-checkbox v-model="row.in_list" />
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('generator.formShow')" width="80" align="center">
                        <template #default="{ row }">
                            <el-checkbox v-model="row.in_form" />
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('generator.searchable')" width="80" align="center">
                        <template #default="{ row }">
                            <el-checkbox v-model="row.searchable" />
                        </template>
                    </el-table-column>
                </el-table>
                <div class="mt-4 flex justify-between">
                    <el-button @click="step = 0">{{ $t('generator.prevStep') }}</el-button>
                    <el-button type="primary" :loading="previewLoading" @click="handlePreview">{{
                        $t('generator.previewCode')
                    }}</el-button>
                </div>
            </el-card>

            <!-- Step 3: 预览 & 生成 -->
            <el-card v-if="step === 2" shadow="never">
                <template #header>{{ $t('generator.previewTitle') }}</template>
                <el-tabs v-model="activeTab">
                    <el-tab-pane
                        v-for="(file, key) in previewFiles"
                        :key="key"
                        :label="key"
                        :name="key"
                    >
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm text-gray-500">{{ file.path }}</span>
                            <el-button size="small" @click="copyCode(file.content)">{{
                                $t('common.copy')
                            }}</el-button>
                        </div>
                        <div class="code-block">
                            <pre><code v-html="highlightCode(file.path, file.content)"></code></pre>
                        </div>
                    </el-tab-pane>
                </el-tabs>
                <div class="mt-4 flex justify-between">
                    <el-button @click="step = 1">{{ $t('generator.prevStep') }}</el-button>
                    <el-button type="primary" :loading="generateLoading" @click="handleGenerate">{{
                        $t('generator.confirmGenerate')
                    }}</el-button>
                </div>
            </el-card>
        </el-dialog>

        <!-- 生成结果 -->
        <el-dialog v-model="showResult" :title="$t('generator.resultTitle')" width="600px">
            <el-table :data="generateResult" size="small" border>
                <el-table-column prop="path" :label="$t('generator.filePath')" min-width="300" />
                <el-table-column
                    prop="status"
                    :label="$t('generator.statusLabel')"
                    width="100"
                    align="center"
                >
                    <template #default="{ row }">
                        <el-tag
                            :type="row.status === 'created' ? 'success' : 'warning'"
                            size="small"
                            >{{
                                row.status === 'created'
                                    ? $t('generator.statusCreated')
                                    : $t('generator.statusSkipped')
                            }}</el-tag
                        >
                    </template>
                </el-table-column>
                <el-table-column prop="reason" :label="$t('generator.reason')" width="120" />
            </el-table>
            <p class="mt-3 text-sm text-gray-500">{{ $t('generator.reloadRequired') }}</p>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import 'highlight.js/styles/atom-one-dark.css'

import { ElMessage } from 'element-plus'
import hljs from 'highlight.js'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { generatorApi } from '@/api/generator'
import type { GeneratorTableInfo } from '@/types/generator'

const { t } = useI18n()

function highlightCode(path: string, content: string): string {
    const extension = path.split('.').pop() || ''
    const language = ({ vue: 'xml', ts: 'typescript' } as Record<string, string>)[extension] || extension
    return hljs.getLanguage(language)
        ? hljs.highlight(content, { language }).value
        : hljs.highlightAuto(content).value
}

async function copyCode(content: string) {
    try {
        const { default: useClipboard } = await import('vue-clipboard3')
        await useClipboard().toClipboard(content)
        ElMessage.success(t('message.copySuccess'))
    } catch {
        ElMessage.error(t('message.copyFailed'))
    }
}

const step = ref(0)
const tableLoading = ref(false)
const tables = ref<any[]>([])
const tableSearch = ref('')
const selectedTable = ref('')
const columns = ref<any[]>([])
const previewLoading = ref(false)
const generateLoading = ref(false)
const previewFiles = ref<Record<string, any>>({})
const activeTab = ref('controller')
const showResult = ref(false)
const generateResult = ref<any[]>([])

const config = reactive({
    table_name: '',
    module_name: '',
    model_name: '',
    table_comment: ''
})

const filteredTables = computed(() => {
    if (!tableSearch.value) return tables.value
    const kw = tableSearch.value.toLowerCase()
    return tables.value.filter(
        (item: any) =>
            item.name.toLowerCase().includes(kw) || (item.comment || '').toLowerCase().includes(kw)
    )
})

const fetchTables = async () => {
    tableLoading.value = true
    try {
        const res = await generatorApi.getTables()
        tables.value = res.data || []
    } finally {
        tableLoading.value = false
    }
}

const handleTableSelect = (row: any) => {
    if (row) {
        selectedTable.value = row.name
        config.table_comment = row.comment || ''
    }
}

const goStep2 = async () => {
    config.table_name = selectedTable.value
    // 自动推断模块名和模型名
    const parts = selectedTable.value.replace(/^[a-z]+_/, '').split('_')
    config.model_name =
        config.model_name ||
        parts.map((p: string) => p.charAt(0).toUpperCase() + p.slice(1)).join('')
    // 不预填 business：那是手写语言分组，生成器会直接拒绝
    config.table_comment = config.table_comment || config.model_name

    try {
        const res = await generatorApi.getColumns(selectedTable.value)
        columns.value = (res.data || []).map((column) => ({
            ...column,
            options_text: (column.options || [])
                .map((option) => `${option.value}=${option.label}`)
                .join('\n')
        }))
        step.value = 1
    } catch (e) {
        ElMessage.error(t('generator.getColumnsFailed'))
    }
}

const configuredColumns = () =>
    columns.value.map(({ options_text, ...column }) => ({
        ...column,
        options: String(options_text || '')
            .split('\n')
            .filter((line) => line.trim() !== '')
            .map((line) => {
                const separator = line.indexOf('=')
                return {
                    value: separator < 0 ? '' : line.slice(0, separator).trim(),
                    label: separator < 0 ? '' : line.slice(separator + 1).trim()
                }
            })
    }))

const handlePreview = async () => {
    if (!config.module_name || !config.model_name) {
        ElMessage.warning(t('generator.fillModuleAndModel'))
        return
    }
    previewLoading.value = true
    try {
        const res = await generatorApi.preview({ ...config, columns: configuredColumns() })
        previewFiles.value = res.data || {}
        activeTab.value = Object.keys(previewFiles.value)[0] || 'controller'
        step.value = 2
    } catch {
        // 预期的字段校验错误已由请求拦截器提示，留在配置页继续修改。
    } finally {
        previewLoading.value = false
    }
}

const handleGenerate = async () => {
    generateLoading.value = true
    try {
        const res = await generatorApi.generate({ ...config, columns: configuredColumns() })
        generateResult.value = res.data.files || []
        showResult.value = true
        ElMessage.success(t('generator.generateSuccess'))
    } catch (error) {
        const files = (error as { data?: { files?: unknown[] } }).data?.files
        if (files) {
            generateResult.value = files
            showResult.value = true
        }
    } finally {
        generateLoading.value = false
    }
}

onMounted(fetchTables)

// Client-side presentation only; API requests and generation payloads stay unchanged.
const tablePage = ref(1)
const tableLimit = ref(20)
function handleTableSizeChange(size: number) {
    tableLimit.value = size
    tablePage.value = 1
}
function configureTable(row: GeneratorTableInfo) {
    handleTableSelect(row)
    goStep2()
}
const pagedTables = computed(() => filteredTables.value.slice((tablePage.value - 1) * tableLimit.value, tablePage.value * tableLimit.value))
const tableEntityName = (name: string) => name.replace(/^[a-z]+_/, '').split('_').map(part => part.charAt(0).toUpperCase() + part.slice(1)).join('')
</script>

<style lang="scss" scoped>
.generator-page {
    width: 100%;
}

.generator-page :deep(.el-card__header) {
    font-size: 14px;
    font-weight: 600;
    color: var(--ink-900);
}

@media (max-width: 768px) {
    .generator-page :deep(.el-form > .el-row > .el-col) {
        flex-basis: 100%;
        max-width: 100%;
    }
}

.tbl-name {
    color: var(--brand-500);
    background: var(--brand-50);
    padding: 3px 7px;
    border-radius: 3px;
    font-size: 12px;
}
.tbl-entity {
    color: var(--ink-600);
    font-size: 12px;
}
.pro-generator-dialog :deep(.el-card) {
    border: 0;
    box-shadow: none;
}
.pro-generator-dialog :deep(.el-card__header) {
    display: none;
}
</style>
