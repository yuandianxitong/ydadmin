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
export interface CompileResult {
    stage_id: string
    dir: string
    schema_patch: string
    update_sql: string
    files: CompileFile[]
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
    applyDev(specId: string, stageId: string) {
        return myRequest.post<{ ddl_applied: boolean; written: string[] }>('/adminapi/system/ydspec/apply-dev', {
            spec_id: specId,
            stage_id: stageId
        })
    }
}
