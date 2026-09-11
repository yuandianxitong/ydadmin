import { describe, expect, it } from 'vitest'

import { countBySeverity, hasBlockingIssues } from '../helpers'

describe('ydspec helpers', () => {
    it('detects blocking issues', () => {
        expect(hasBlockingIssues([{ ref: 'A.x', rule: 'r', severity: 'error', message: 'm' }])).toBe(true)
        expect(hasBlockingIssues([{ ref: 'A.x', rule: 'r', severity: 'warn', message: 'm' }])).toBe(false)
    })
    it('counts by severity', () => {
        const issues = [
            { ref: 'A.x', rule: 'r', severity: 'error' as const, message: 'm' },
            { ref: 'A.y', rule: 'r', severity: 'warn' as const, message: 'm' }
        ]
        expect(countBySeverity(issues)).toEqual({ error: 1, warn: 1 })
    })
})
