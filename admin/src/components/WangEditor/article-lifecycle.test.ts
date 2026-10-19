import { flushPromises, mount } from '@vue/test-utils'
import { SlateEditor } from '@wangeditor/editor'
import type { FormInstance } from 'element-plus'
import { expect, it, vi } from 'vitest'
import { defineComponent, nextTick, ref } from 'vue'

import { useFormDialog } from '@/hooks/useFormDialog'

import WangEditor from './index.vue'

const request = vi.hoisted(() => ({ post: vi.fn() }))
vi.mock('element-plus', () => ({ ElMessage: { success: vi.fn() } }))
vi.mock('@/utils/request', () => ({ myRequest: request }))
vi.mock('@/utils/i18n', () => ({ t: (key: string) => key }))

it('saves text after an image and destroys the Slate editor before resetting the article', async () => {
    const errors: unknown[] = []
    const update = vi.fn().mockResolvedValue({})
    const ArticleDialog = defineComponent({
        components: { WangEditor },
        setup() {
            const open = ref(true)
            const dialog = useFormDialog({
                defaultForm: {
                    id: 7,
                    content:
                        '<p>before</p><p><img src="https://example.test/image.png" /></p><p>after</p>'
                },
                modelValue: () => open.value,
                onUpdate: (value) => {
                    open.value = value
                },
                createFn: vi.fn(),
                updateFn: update,
                resetOnClose: false
            })
            dialog.formRef.value = {
                validate: vi.fn().mockResolvedValue(true)
            } as unknown as FormInstance
            return dialog
        },
        template: '<WangEditor v-if="visible" v-model="form.content" />'
    })
    const wrapper = mount(ArticleDialog, {
        attachTo: document.body,
        global: { config: { errorHandler: (error) => errors.push(error) } }
    })
    try {
        await flushPromises()
        const editor = (
            wrapper.findComponent(WangEditor).vm as unknown as {
                editorRef: import('@wangeditor/editor').IDomEditor
            }
        ).editorRef
        editor.select(SlateEditor.end(editor, []))
        const image = {
            type: 'image',
            src: 'https://example.test/image.png',
            alt: '',
            href: '',
            children: [{ text: '' }]
        }
        editor.insertNode(image)
        await nextTick()
        expect(editor.getHtml()).toContain('<img')
        editor.select(SlateEditor.end(editor, []))
        editor.insertText(' edited')
        await nextTick()
        await (wrapper.vm as unknown as { handleSubmit: () => Promise<void> }).handleSubmit()
        await flushPromises()
        expect(update).toHaveBeenCalledOnce()
        expect(update.mock.calls[0][1].content).toContain(' edited')
        expect(editor.isDestroyed).toBe(true)
        expect(errors).toEqual([])
    } finally {
        wrapper.unmount()
    }
})

it('dismisses the image group after uploading and allows opening it again', async () => {
    const wrapper = mount(WangEditor, { attachTo: document.body })
    try {
        await flushPromises()
        const editor = (
            wrapper.vm as unknown as { editorRef: import('@wangeditor/editor').IDomEditor }
        ).editorRef
        const config = editor.getMenuConfig('uploadImage') as {
            customUpload: (file: File, insert: (url: string) => void) => Promise<void>
        }
        request.post.mockResolvedValue({ data: { url: '/storage/uploaded.png' } })
        const insert = vi.fn()
        await config.customUpload(new File(['image'], 'image.png', { type: 'image/png' }), insert)
        const group = wrapper.element
            .querySelector('[data-menu-key="uploadImage"]')
            ?.closest('.w-e-bar-item-group')
        expect(insert).toHaveBeenCalledWith('/storage/uploaded.png', '', '')
        expect(group?.classList.contains('upload-menu-dismissed')).toBe(true)
        group?.dispatchEvent(new MouseEvent('mouseleave'))
        expect(group?.classList.contains('upload-menu-dismissed')).toBe(false)
    } finally {
        wrapper.unmount()
    }
})
