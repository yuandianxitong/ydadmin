import type { SpecIssue } from '@/types/ydspec'

export function hasBlockingIssues(issues: SpecIssue[]): boolean {
    return issues.some((i) => i.severity === 'error')
}

export function countBySeverity(issues: SpecIssue[]): { error: number; warn: number } {
    return {
        error: issues.filter((i) => i.severity === 'error').length,
        warn: issues.filter((i) => i.severity === 'warn').length
    }
}
