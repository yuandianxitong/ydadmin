import type { FormInstance } from 'element-plus'
import { describe, expect, it, vi } from 'vitest'

import { useFormDialog } from '../useFormDialog'

vi.mock('element-plus', () => ({ ElMessage: { success: vi.fn() } }))
vi.mock('@/utils/i18n', () => ({ t: (key: string) => key }))

describe('rich text dialog lifecycle', () => {
    it.each([true, false])(
        'keeps editor content until closed after save (editing=%s)',
        async (editing) => {
            let open = true
            const success = vi.fn()
            const create = vi.fn().mockResolvedValue({})
            const update = vi.fn().mockResolvedValue({})
            const dialog = useFormDialog({
                defaultForm: { id: undefined as number | undefined, content: '' },
                modelValue: () => open,
                onUpdate: (value) => {
                    open = value
                },
                onSuccess: success,
                createFn: create,
                updateFn: update,
                resetOnClose: false
            })
            dialog.formRef.value = {
                validate: vi.fn().mockResolvedValue(true),
                resetFields: vi.fn()
            } as unknown as FormInstance
            dialog.form.id = editing ? 42 : undefined
            dialog.form.content = '<p>text<img src="/storage/example.png">edited</p>'
            await dialog.handleSubmit()

            expect(open).toBe(false)
            expect(success).toHaveBeenCalledOnce()
            expect(editing ? update : create).toHaveBeenCalledOnce()
            expect(dialog.form.content).toContain('<img')
            dialog.resetForm() // @closed: editor 已卸载
            expect(dialog.form.content).toBe('')
        }
    )

    it('also defers resetting when cancelling the drawer', () => {
        const dialog = useFormDialog({
            defaultForm: { id: undefined, content: '' },
            modelValue: () => true,
            onUpdate: vi.fn(),
            createFn: vi.fn(),
            updateFn: vi.fn(),
            resetOnClose: false
        })
        dialog.form.content = '<p>unsaved</p>'
        dialog.handleClose()
        expect(dialog.form.content).toBe('<p>unsaved</p>')
    })
})
