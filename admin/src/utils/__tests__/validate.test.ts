import { describe, expect, it } from 'vitest'

import { isExternal } from '../validate'

describe('isExternal', () => {
    it('should return true for http URLs', () => {
        expect(isExternal('https://www.example.com')).toBe(true)
        expect(isExternal('http://localhost:3000')).toBe(true)
    })

    it('should return true for mailto links', () => {
        expect(isExternal('mailto:test@example.com')).toBe(true)
    })

    it('should return true for tel links', () => {
        expect(isExternal('tel:+1234567890')).toBe(true)
    })

    it('should return false for internal paths', () => {
        expect(isExternal('/dashboard')).toBe(false)
        expect(isExternal('/system/admin')).toBe(false)
        expect(isExternal('dashboard')).toBe(false)
    })

    it('should return false for empty string', () => {
        expect(isExternal('')).toBe(false)
    })
})
