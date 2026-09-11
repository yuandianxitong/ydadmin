import type { SpecExplanation, SpecIssue, SpecQuestion, YdSpec } from '@/types/ydspec'
import { myRequest } from '@/utils/request'

export interface SpecVersions {
    prompt: string
    model: string
}
export interface SpecRefineResult {
    draft_spec: YdSpec
    questions: SpecQuestion[]
    explanations: SpecExplanation[]
    issues: SpecIssue[]
    versions: SpecVersions | null
}
export interface CompileFile {
    path: string
    bytes: number
}
export interface CheckResultItem {
    check: string
    severity: 'error' | 'warning' | 'skipped'
    message: string
    ref: string | null
}
export interface CheckSummary {
    passed: boolean
    error_count: number
    warning_count: number
    skipped: string[]
    results: CheckResultItem[]
}
export interface CompileResult {
    artifact_id: number
    stage_id: string
    dir: string
    schema_patch: string
    update_sql: string
    files: CompileFile[]
    check_summary: CheckSummary
}
export interface Artifact {
    id: number
    spec_id: string
    stage_id: string
    module: string
    title: string
    state: string
    check_summary: CheckSummary | null
    created_at?: string
}

export const ydspecApi = {
    refine(payload: { description: string; answers?: Record<string, string>; draft?: YdSpec | null }) {
        return myRequest.post<SpecRefineResult>('/adminapi/system/ydspec/refine', {
            description: payload.description,
            answers: payload.answers ?? {},
            draft: payload.draft ?? null
        })
    },
    confirm(spec: YdSpec, versions?: SpecVersions | null) {
        return myRequest.post<{ spec_id: string; path: string }>('/adminapi/system/ydspec/confirm', {
            spec,
            versions: versions ?? null
        })
    },
    compile(specId: string) {
        return myRequest.post<CompileResult>('/adminapi/system/ydspec/compile', { spec_id: specId })
    },
    listArtifacts(specId: string) {
        return myRequest.get<Artifact[]>('/adminapi/system/ydspec/artifacts', { params: { spec_id: specId } })
    },
    recheck(artifactId: number) {
        return myRequest.post<{ artifact_id: number; state: string; check_summary: CheckSummary }>(`/adminapi/system/ydspec/artifacts/recheck/${artifactId}`, {})
    },
    apply(artifactId: number) {
        return myRequest.post<{ applied: boolean; written: string[] }>(`/adminapi/system/ydspec/artifacts/apply/${artifactId}`, {})
    }
}
