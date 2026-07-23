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
    }
}
