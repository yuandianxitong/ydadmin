import type { SpecExplanation, SpecIssue, SpecQuestion, YdSpec } from '@/types/ydspec'
import { myRequest } from '@/utils/request'

export interface SpecRefineResult {
    draft_spec: YdSpec
    questions: SpecQuestion[]
    explanations: SpecExplanation[]
    issues: SpecIssue[]
}

export const ydspecApi = {
    refine(payload: { description: string; answers?: Record<string, string>; draft?: YdSpec | null }) {
        return myRequest.post<SpecRefineResult>('/adminapi/system/ydspec/refine', {
            description: payload.description,
            answers: payload.answers ?? {},
            draft: payload.draft ?? null
        })
    },
    confirm(spec: YdSpec) {
        return myRequest.post<{ spec_id: string; path: string }>('/adminapi/system/ydspec/confirm', { spec })
    }
}
