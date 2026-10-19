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
})
