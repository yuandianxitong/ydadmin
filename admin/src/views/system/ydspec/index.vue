<script setup lang="ts" name="YdSpecWizard">
import { ref } from 'vue'
import { ElMessage } from 'element-plus'

import { ydspecApi } from '@/api/ydspec'
import type { SpecVersions, CompileResult, Artifact } from '@/api/ydspec'
import type { SpecExplanation, SpecIssue, SpecQuestion, YdSpec } from '@/types/ydspec'
import { countBySeverity, hasBlockingIssues } from './helpers'

const step = ref(0)
const loading = ref(false)
const description = ref('')
const draft = ref<YdSpec | null>(null)
const draftText = ref('')
const questions = ref<SpecQuestion[]>([])
const explanations = ref<SpecExplanation[]>([])
const issues = ref<SpecIssue[]>([])
const answers = ref<Record<string, string>>({})
const versions = ref<SpecVersions | null>(null)
const specId = ref('')
const compileResult = ref<CompileResult | null>(null)
const artifacts = ref<Artifact[]>([])
const artifactsLoaded = ref(false)

function resetAll() {
    step.value = 0
    description.value = ''
    draft.value = null
    draftText.value = ''
    questions.value = []
    explanations.value = []
    issues.value = []
    answers.value = {}
    versions.value = null
    specId.value = ''
    compileResult.value = null
    artifacts.value = []
    artifactsLoaded.value = false
}

async function runRefine() {
    if (!description.value.trim()) {
        ElMessage.warning('请先描述业务')
        return
    }
    loading.value = true
    try {
        const res = await ydspecApi.refine({
            description: description.value,
            answers: answers.value,
            draft: draft.value
        })
        draft.value = res.data.draft_spec
        draftText.value = JSON.stringify(res.data.draft_spec, null, 2)
        questions.value = res.data.questions
        explanations.value = res.data.explanations
        issues.value = res.data.issues
        versions.value = res.data.versions ?? null
        step.value = 1
    } finally {
        loading.value = false
    }
}

function syncDraftFromText(): boolean {
    try {
        draft.value = JSON.parse(draftText.value) as YdSpec
        return true
    } catch {
        ElMessage.error('规格 JSON 格式有误，请检查')
        return false
    }
}

async function confirmSpec() {
    if (!syncDraftFromText() || !draft.value) return
    if (hasBlockingIssues(issues.value)) {
        ElMessage.error('仍有阻断级问题（error），请先修正后再确认')
        return
    }
    loading.value = true
    try {
        const res = await ydspecApi.confirm(draft.value, versions.value)
        specId.value = res.data.spec_id
        ElMessage.success(`规格已保存：${res.data.path}`)
        step.value = 2
    } finally {
        loading.value = false
    }
}

async function runCompile() {
    if (!specId.value) return
    loading.value = true
    try {
        const res = await ydspecApi.compile(specId.value)
        compileResult.value = res.data
        if (res.data.check_summary.passed) {
            ElMessage.success('编译完成，检查通过')
        } else {
            ElMessage.warning('编译完成，但检查未通过，无法应用')
        }
        await loadArtifacts()
    } finally {
        loading.value = false
    }
}

async function loadArtifacts() {
    if (!specId.value) return
    try {
        const res = await ydspecApi.listArtifacts(specId.value)
        artifacts.value = res.data
        artifactsLoaded.value = true
    } catch {
        // 历史列表非关键路径，静默失败即可
    }
}

async function runRecheck() {
    if (!compileResult.value) return
    loading.value = true
    try {
        const res = await ydspecApi.recheck(compileResult.value.artifact_id)
        compileResult.value = { ...compileResult.value, check_summary: res.data.check_summary }
        ElMessage.success('已重新检查')
        await loadArtifacts()
    } finally {
        loading.value = false
    }
}

async function runApply() {
    if (!compileResult.value) return
    loading.value = true
    try {
        const res = await ydspecApi.apply(compileResult.value.artifact_id)
        ElMessage.success(`已应用，写入 ${res.data.written.length} 个文件`)
        await loadArtifacts()
    } finally {
        loading.value = false
    }
}

function tagType(sev: SpecIssue['severity']) {
    return sev === 'error' ? 'danger' : 'warning'
}
</script>

<template>
    <div class="ydspec-wizard">
        <el-steps :active="step" finish-status="success" simple>
            <el-step title="描述业务" />
            <el-step title="精炼与校验" />
            <el-step title="完成" />
        </el-steps>

        <el-card v-if="step === 0" shadow="never" class="mt-4">
            <el-input
                v-model="description"
                type="textarea"
                :rows="6"
                placeholder="用一句话到一段话描述你的业务，例如：我要做一个预约服务，用户预约门店的服务项目……"
            />
            <div class="mt-3">
                <el-button type="primary" :loading="loading" @click="runRefine">生成规格</el-button>
            </div>
        </el-card>

        <el-card v-else-if="step === 1" shadow="never" class="mt-4">
            <el-alert
                v-if="issues.length"
                :title="`校验：${countBySeverity(issues).error} 个错误 / ${countBySeverity(issues).warn} 个提醒`"
                :type="hasBlockingIssues(issues) ? 'error' : 'warning'"
                :closable="false"
                class="mb-3"
            />
            <el-table v-if="issues.length" :data="issues" size="small" class="mb-4">
                <el-table-column label="字段" prop="ref" width="220" />
                <el-table-column label="级别" width="90">
                    <template #default="{ row }">
                        <el-tag :type="tagType(row.severity)" size="small">{{ row.severity }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="说明" prop="message" />
            </el-table>

            <el-form v-if="questions.length" label-position="top" class="mb-4">
                <el-form-item v-for="q in questions" :key="q.id" :label="q.text">
                    <div v-if="q.why" class="q-why">{{ q.why }}</div>
                    <el-select v-if="q.kind === 'choice'" v-model="answers[q.id]" placeholder="请选择">
                        <el-option v-for="opt in q.options" :key="opt" :label="opt" :value="opt" />
                    </el-select>
                    <el-switch v-else-if="q.kind === 'boolean'" v-model="answers[q.id]" active-value="是" inactive-value="否" />
                    <el-input v-else v-model="answers[q.id]" />
                </el-form-item>
            </el-form>

            <el-input v-model="draftText" type="textarea" :rows="16" spellcheck="false" />

            <el-table v-if="explanations.length" :data="explanations" size="small" class="mt-3">
                <el-table-column label="字段" prop="ref" width="220" />
                <el-table-column label="为什么建议" prop="rationale" />
                <el-table-column label="风险" prop="risk" />
            </el-table>

            <div class="mt-3">
                <el-button :loading="loading" @click="runRefine">按回答再精炼一轮</el-button>
                <el-button type="primary" :loading="loading" @click="confirmSpec">确认并保存规格</el-button>
            </div>
        </el-card>

        <el-card v-else shadow="never" class="mt-4">
            <el-result icon="success" title="规格已保存" sub-title="可编译为数据库 DDL + CRUD 代码">
                <template #extra>
                    <el-button type="primary" :loading="loading" @click="runCompile">编译预览</el-button>
                    <el-button @click="resetAll">再建一个</el-button>
                </template>
            </el-result>

            <template v-if="compileResult">
                <el-divider content-position="left">检查</el-divider>
                <el-alert
                    :title="compileResult.check_summary.passed
                        ? `检查通过，可应用：${compileResult.check_summary.error_count} error / ${compileResult.check_summary.warning_count} warning / ${compileResult.check_summary.skipped.length} skipped`
                        : `检查未通过：${compileResult.check_summary.error_count} error / ${compileResult.check_summary.warning_count} warning / ${compileResult.check_summary.skipped.length} skipped`"
                    :type="compileResult.check_summary.passed ? 'success' : 'error'"
                    :closable="false"
                    class="mb-3"
                />
                <div class="mb-3">
                    <el-button size="small" :loading="loading" @click="runRecheck">重新检查</el-button>
                    <el-button
                        type="primary"
                        size="small"
                        :loading="loading"
                        :disabled="!compileResult.check_summary.passed"
                        @click="runApply"
                    >应用到开发环境</el-button>
                    <span v-if="!compileResult.check_summary.passed" class="q-why">检查未通过，无法应用</span>
                </div>
                <el-table
                    v-if="compileResult.check_summary.results.length"
                    :data="compileResult.check_summary.results"
                    size="small"
                    class="mb-4"
                >
                    <el-table-column label="检查" prop="check" width="160" />
                    <el-table-column label="级别" width="100">
                        <template #default="{ row }">
                            <el-tag
                                :type="row.severity === 'error' ? 'danger' : (row.severity === 'warning' ? 'warning' : 'info')"
                                size="small"
                            >{{ row.severity }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="位置" prop="ref" width="180">
                        <template #default="{ row }">
                            {{ row.ref ?? '-' }}
                        </template>
                    </el-table-column>
                    <el-table-column label="说明" prop="message" />
                </el-table>

                <el-divider content-position="left">DDL（schema_patch.sql）</el-divider>
                <el-input :model-value="compileResult.schema_patch" type="textarea" :rows="14" readonly spellcheck="false" />

                <el-divider content-position="left">生成文件（{{ compileResult.files.length }}）</el-divider>
                <el-table :data="compileResult.files" size="small">
                    <el-table-column label="文件" prop="path" />
                    <el-table-column label="字节" prop="bytes" width="120" />
                </el-table>

                <el-divider content-position="left">编译历史</el-divider>
                <div class="mb-3">
                    <el-button size="small" :loading="loading" @click="loadArtifacts">查看历史</el-button>
                </div>
                <el-table v-if="artifacts.length" :data="artifacts" size="small">
                    <el-table-column label="ID" prop="id" width="80" />
                    <el-table-column label="标题" prop="title" />
                    <el-table-column label="状态" prop="state" width="120" />
                    <el-table-column label="检查结果" width="220">
                        <template #default="{ row }">
                            <span v-if="row.check_summary">
                                {{ row.check_summary.error_count }} error / {{ row.check_summary.warning_count }} warning / {{ row.check_summary.skipped.length }} skipped
                            </span>
                            <span v-else>-</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="创建时间" prop="created_at" width="180" />
                </el-table>
                <el-empty v-else-if="artifactsLoaded" description="暂无历史记录" :image-size="60" />
            </template>
        </el-card>
    </div>
</template>

<style scoped>
.ydspec-wizard { padding: 16px; }
.mt-3 { margin-top: 12px; }
.mt-4 { margin-top: 16px; }
.mb-3 { margin-bottom: 12px; }
.mb-4 { margin-bottom: 16px; }
.q-why { color: var(--el-text-color-secondary); font-size: 12px; margin-bottom: 6px; }
</style>
