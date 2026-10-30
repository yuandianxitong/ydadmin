import { afterEach, describe, expect, it } from 'vitest'

import { applyDarkClass, setTheme } from '../theme'

describe('saved theme colors', () => {
    afterEach(() => {
        document.documentElement.removeAttribute('style')
        applyDarkClass(false)
    })

    it('applies primary and derived colors inline in dark mode', () => {
        applyDarkClass(true)
        setTheme({ primary: '#e67e22' }, true)
        const style = document.documentElement.style
        expect(style.getPropertyValue('--el-color-primary')).toBe('#e67e22')
        expect(style.getPropertyValue('--el-color-primary-light-9')).not.toBe('')
        expect(style.getPropertyValue('--color-brand')).toBe('#e67e22')
        expect(style.getPropertyValue('--color-brand-ghost')).toContain('0.15')
        const darkShade = style.getPropertyValue('--el-color-primary-light-9')
        setTheme({ primary: '#e67e22' }, false)
        expect(style.getPropertyValue('--el-color-primary-light-9')).not.toBe(darkShade)
    })

    it.each([
        ['#4f6bff', '#eef1ff'],
        ['#0ea5e9', '#e0f2fe'],
        ['#14b8a6', '#e6fbf7'],
        ['#8b5cf6', '#f3edff'],
        ['#f97316', '#fff4e9']
    ])('keeps the Pro light palette for preset %s', (color, tint) => {
        setTheme({ primary: color }, false)
        expect(document.documentElement.style.getPropertyValue('--el-color-primary')).toBe(color)
        expect(document.documentElement.style.getPropertyValue('--el-color-primary-light-9')).toBe(
            tint
        )
    })
})
