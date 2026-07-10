<template>
    <div class="ai-studio-page flex gap-4">
        <!-- 左栏：配置区 -->
        <el-card class="left-panel" shadow="never">
            <template #header>生成配置</template>

            <el-form label-position="top">
                <el-form-item label="数据表">
                    <el-select
                        v-model="selectedTables"
                        multiple
                        filterable
                        :multiple-limit="MAX_TABLES"
                        placeholder="请选择数据表（最多 5 张）"
                        :loading="tableLoading"
                        style="width: 100%"
                    >
                        <el-option
                            v-for="item in tables"
                            :key="item.name"
                            :label="item.comment ? `${item.name}（${item.comment}）` : item.name"
                            :value="item.name"
                        />
                    </el-select>
                </el-form-item>

                <el-form-item label="生成类型">
                    <el-radio-group v-model="genType">
                        <el-radio value="crud">CRUD 增强</el-radio>
                        <el-radio value="feature">功能扩展</el-radio>
                        <el-radio value="api">API 接口</el-radio>
                    </el-radio-group>
                </el-form-item>

                <el-form-item label="需求描述">
                    <el-input
                        v-model="instruction"
                        type="textarea"
                        :rows="8"
                        :maxlength="500"
                        show-word-limit
                        :placeholder="instructionPlaceholder"
                    />
                </el-form-item>

                <el-form-item>
                    <el-button
                        type="primary"
                        :disabled="generating"
                        :loading="generating"
                        style="width: 100%"
                        @click="handleGenerate"
                    >
                        {{ generating ? '生成中…' : '开始生成' }}
                    </el-button>
                </el-form-item>
                <el-form-item v-if="generating">
                    <el-button style="width: 100%" @click="handleStop">停止</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 右栏：输出区（流式 / 完成 / 错误 三态） -->
        <el-card class="right-panel flex-1" shadow="never">
            <template #header>生成结果</template>

            <!-- 空闲态 -->
            <el-empty v-if="phase === 'idle'" description="请在左侧配置需求后点击「开始生成」" />

            <!-- 流式态 -->
            <div v-else-if="phase === 'streaming'">
                <div ref="streamBoxRef" class="stream-box">
                    <pre>{{ streamText }}</pre>
                </div>
                <el-alert
                    v-if="stopped"
                    class="mt-3"
                    type="info"
                    title="已停止生成"
                    :closable="false"
                    show-icon
                />
            </div>

            <!-- 完成态 -->
            <div v-else-if="phase === 'done' && doneData">
                <el-alert
                    v-if="doneData.skipped.length"
                    class="mb-3"
                    type="warning"
                    :closable="false"
                    show-icon
                >
                    <template #title
                        >以下文件路径不合法，已跳过：{{ doneData.skipped.join('、') }}</template
                    >
                </el-alert>

                <el-tabs v-model="activeFileTab" @tab-change="handleTabChange">
                    <el-tab-pane
                        v-for="file in doneData.files"
                        :key="file.path"
                        :label="tabLabel(file)"
                        :name="file.path"
                    >
                        <div class="text-sm text-gray-500 mb-2">{{ file.path }}</div>
                        <div v-loading="fileLoading[file.path]" class="code-block">
                            <pre><code v-html="highlightCode(file.path)"></code></pre>
                        </div>
                    </el-tab-pane>
                </el-tabs>

                <div class="toolbar flex items-center gap-3 flex-wrap mt-4">
                    <el-button :loading="diffLoading" @click="openDiff">查看 Diff</el-button>

                    <el-divider direction="vertical" />

                    <span class="text-sm text-gray-500">选择写入文件：</span>
                    <el-checkbox-group v-model="selectedFiles">
                        <el-checkbox
                            v-for="file in doneData.files"
                            :key="file.path"
                            :value="file.path"
                        >
                            {{ tabLabel(file) }}
                        </el-checkbox>
                    </el-checkbox-group>

                    <el-popconfirm
                        confirm-button-text="确认写入"
                        cancel-button-text="取消"
                        width="320"
                        @confirm="handleApply"
                    >
                        <template #reference>
                            <el-button
                                v-has-perm="['ai.studio.apply']"
                                type="primary"
                                :disabled="!selectedFiles.length"
                                :loading="applying"
                            >
                                写入选中文件
                            </el-button>
                        </template>
                        <template #title>
                            <div>确认写入以下 {{ selectedFiles.length }} 个文件？</div>
                            <div v-for="p in selectedFiles" :key="p" class="text-xs text-gray-500">
                                {{ p }}
                            </div>
                        </template>
                    </el-popconfirm>

                    <el-divider direction="vertical" />

                    <el-button
                        :disabled="!!feedbackGiven"
                        :loading="feedbackSubmitting === 'accepted'"
                        @click="sendFeedback('accepted')"
                    >
                        👍 有用
                    </el-button>
                    <el-button
                        :disabled="!!feedbackGiven"
                        :loading="feedbackSubmitting === 'rejected'"
                        @click="sendFeedback('rejected')"
                    >
                        👎 没用
                    </el-button>
                </div>
            </div>

            <!-- 错误态 -->
            <div v-else-if="phase === 'error'">
                <el-alert
                    type="error"
                    :title="errorMessage || '生成失败'"
                    :closable="false"
                    show-icon
                />
                <div class="mt-4">
                    <el-button type="primary" @click="handleGenerate">重试</el-button>
                </div>
            </div>
        </el-card>

        <!-- Diff 弹窗 -->
        <el-dialog v-model="diffVisible" title="Diff 预览" width="800px">
            <div class="code-block">
                <pre>{{ diffText }}</pre>
            </div>
        </el-dialog>
    </div>
</template>

<script setup lang="ts" name="AiStudioIndex">
import 'highlight.js/styles/atom-one-dark.css'

import { ElMessage } from 'element-plus'
import hljs from 'highlight.js'
import { computed, nextTick, onMounted, ref } from 'vue'

import { aiStudioApi, type StageFile, type StreamDoneData, streamGenerate } from '@/api/ai-studio'
import { generatorApi } from '@/api/generator'
import type { GeneratorTableInfo } from '@/types/generator'

const MAX_TABLES = 5

type GenType = 'crud' | 'feature' | 'api'
type Phase = 'idle' | 'streaming' | 'done' | 'error'

// ========== 左栏：配置 ==========
const tables = ref<GeneratorTableInfo[]>([])
const tableLoading = ref(false)
const selectedTables = ref<string[]>([])
const genType = ref<GenType>('crud')
const instruction = ref('')
const generating = ref(false)
const abortController = ref<AbortController | null>(null)

const placeholderMap: Record<GenType, string> = {
    crud: '例如：为 brands 表生成完整的品牌管理模块，包含列表/新增/修改/删除/状态切换',
    feature: '例如：为已有的订单模块新增导出 Excel 功能',
    api: '例如：为 brands 表生成只读的 API 查询接口，供小程序调用'
}
const instructionPlaceholder = computed(() => placeholderMap[genType.value])

const fetchTables = async () => {
    tableLoading.value = true
    try {
        const res = await generatorApi.getTables()
        tables.value = res.data || []
    } finally {
        tableLoading.value = false
    }
}

function validate(): boolean {
    if (!instruction.value.trim()) {
        ElMessage.warning('请填写需求描述')
        return false
    }
    if (instruction.value.length > 500) {
        ElMessage.warning('需求描述不能超过 500 字')
        return false
    }
    if (!selectedTables.value.length) {
        ElMessage.warning('请至少选择一张数据表')
        return false
    }
    if (selectedTables.value.length > MAX_TABLES) {
        ElMessage.warning(`最多选择 ${MAX_TABLES} 张数据表`)
        return false
    }
    return true
}

// ========== 右栏：三态输出 ==========
const phase = ref<Phase>('idle')
const streamText = ref('')
const streamBoxRef = ref<HTMLElement | null>(null)
const stopped = ref(false)
const doneData = ref<StreamDoneData | null>(null)
const errorMessage = ref('')

function scrollStreamToBottom() {
    nextTick(() => {
        if (streamBoxRef.value) {
            streamBoxRef.value.scrollTop = streamBoxRef.value.scrollHeight
        }
    })
}

async function handleGenerate() {
    if (!validate()) return

    phase.value = 'streaming'
    streamText.value = ''
    stopped.value = false
    doneData.value = null
    errorMessage.value = ''
    selectedFiles.value = []
    fileCode.value = {}
    activeFileTab.value = ''
    feedbackGiven.value = ''
    generating.value = true
    abortController.value = new AbortController()

    try {
        await streamGenerate(
            {
                instruction: instruction.value.trim(),
                tables: selectedTables.value,
                gen_type: genType.value
            },
            {
                onChunk: (text) => {
                    streamText.value += text
                    scrollStreamToBottom()
                },
                onDone: (data) => {
                    doneData.value = data
                    phase.value = 'done'
                    activeFileTab.value = data.files[0]?.path ?? ''
                    if (activeFileTab.value) loadPreview(activeFileTab.value)
                },
                onError: (message) => {
                    errorMessage.value = message
                    phase.value = 'error'
                }
            },
            abortController.value.signal
        )
    } catch (e: any) {
        if (e?.name === 'AbortError') {
            stopped.value = true
        } else {
            errorMessage.value = e?.message || '生成失败，请稍后重试'
            phase.value = 'error'
        }
    } finally {
        generating.value = false
        abortController.value = null
    }
}

function handleStop() {
    abortController.value?.abort()
}

// ========== 完成态：文件预览 / 高亮 ==========
const activeFileTab = ref('')
const fileCode = ref<Record<string, string>>({})
const fileLoading = ref<Record<string, boolean>>({})

function tabLabel(file: StageFile): string {
    const seg = file.path.split('/').pop() || file.path
    return file.exists ? `${seg} [覆盖]` : seg
}

async function loadPreview(path: string) {
    if (!doneData.value || fileCode.value[path] !== undefined) return
    fileLoading.value = { ...fileLoading.value, [path]: true }
    try {
        const res = await aiStudioApi.preview(doneData.value.stage_id, path)
        fileCode.value = { ...fileCode.value, [path]: res.data.code }
    } catch {
        fileCode.value = { ...fileCode.value, [path]: '' }
    } finally {
        fileLoading.value = { ...fileLoading.value, [path]: false }
    }
}

function handleTabChange(name: string | number) {
    loadPreview(String(name))
}

function detectLang(path: string): string {
    const ext = path.split('.').pop()?.toLowerCase() || ''
    const map: Record<string, string> = {
        php: 'php',
        ts: 'typescript',
        js: 'javascript',
        vue: 'xml',
        json: 'json'
    }
    return map[ext] || ''
}

function escapeHtml(text: string): string {
    return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

function highlightCode(path: string): string {
    const code = fileCode.value[path]
    if (!code) return ''
    try {
        const lang = detectLang(path)
        if (lang && hljs.getLanguage(lang)) {
            return hljs.highlight(code, { language: lang }).value
        }
        return hljs.highlightAuto(code).value
    } catch {
        return escapeHtml(code)
    }
}

// ========== 完成态：Diff ==========
const diffVisible = ref(false)
const diffLoading = ref(false)
const diffText = ref('')

async function openDiff() {
    if (!doneData.value) return
    diffVisible.value = true
    if (diffText.value) return
    diffLoading.value = true
    try {
        const res = await aiStudioApi.diff(doneData.value.stage_id)
        diffText.value = res.data.diff
    } finally {
        diffLoading.value = false
    }
}

// ========== 完成态：写入选中文件 ==========
const selectedFiles = ref<string[]>([])
const applying = ref(false)

async function handleApply() {
    if (!doneData.value || !selectedFiles.value.length) return
    applying.value = true
    try {
        const res = await aiStudioApi.apply(doneData.value.stage_id, selectedFiles.value)
        ElMessage.success(`已写入 ${res.data.written.length} 个文件`)
        selectedFiles.value = []
    } finally {
        applying.value = false
    }
}

// ========== 完成态：反馈 ==========
const feedbackGiven = ref<'' | 'accepted' | 'rejected'>('')
const feedbackSubmitting = ref<'' | 'accepted' | 'rejected'>('')

async function sendFeedback(action: 'accepted' | 'rejected') {
    if (!doneData.value?.generation_id || feedbackGiven.value) return
    feedbackSubmitting.value = action
    try {
        await aiStudioApi.feedback(doneData.value.generation_id, action)
        feedbackGiven.value = action
        ElMessage.success('感谢反馈')
    } finally {
        feedbackSubmitting.value = ''
    }
}

onMounted(fetchTables)
</script>

<style lang="scss" scoped>
.ai-studio-page {
    width: 100%;
    align-items: flex-start;
}

.left-panel {
    width: 380px;
    flex-shrink: 0;
}

.right-panel {
    min-width: 0;
}

.stream-box {
    background: var(--gray-100);
    border: 1px solid var(--color-border);
    border-radius: 4px;
    padding: 16px;
    height: 520px;
    overflow: auto;

    pre {
        margin: 0;
        white-space: pre-wrap;
        word-wrap: break-word;
        font-family: 'Consolas', 'Monaco', monospace;
        font-size: 13px;
        line-height: 1.6;
    }
}

.code-block {
    background: #282c34;
    color: #abb2bf;
    border-radius: 4px;
    padding: 16px;
    max-height: 500px;
    overflow: auto;

    pre {
        margin: 0;
        white-space: pre-wrap;
        word-wrap: break-word;
        font-family: 'Consolas', 'Monaco', monospace;
        font-size: 13px;
        line-height: 1.6;

        code {
            white-space: pre;
        }
    }
}
</style>
