<script setup lang="ts" name="YdSpecWizard">
import { ref } from 'vue'
import { ElMessage } from 'element-plus'

import { ydspecApi } from '@/api/ydspec'
import type { SpecVersions } from '@/api/ydspec'
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
        ElMessage.success(`规格已保存：${res.data.path}`)
        step.value = 2
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

        <el-result v-else icon="success" title="规格已保存" sub-title="可交给下一步（Spec → make:crud 编译）使用">
            <template #extra>
                <el-button type="primary" @click="resetAll">再建一个</el-button>
            </template>
        </el-result>
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
