import { flushPromises, mount } from '@vue/test-utils'
import { expect, it, vi } from 'vitest'
import { createI18n } from 'vue-i18n'

import ArticleForm from './ArticleForm.vue'

vi.mock('@/store', () => ({ useAppStore: () => ({ getImageUrl: (url: string) => url }) }))
vi.mock('@/utils/auth', () => ({ getToken: () => '' }))
vi.mock('@/api/article', () => ({ articleApi: {} }))
vi.mock('@/utils/i18n', () => ({ t: (key: string) => key }))

it('shows each parent and child category once when opening the article selector', async () => {
    const wrapper = mount(ArticleForm, {
        attachTo: document.body,
        props: {
            modelValue: true,
            formData: {},
            categoryOptions: [{ id: 1001, name: '唯一父分类', children: [{ id: 1002, name: '唯一子分类', children: [] }] }]
        },
        global: {
            plugins: [createI18n({ legacy: false, locale: 'zh-CN', missingWarn: false, fallbackWarn: false })],
            stubs: { WangEditor: true, Transition: false }
        }
    })
    try {
        await flushPromises()
        const select = wrapper.findComponent({ name: 'ElTreeSelect' })
        await select.find('.el-select__wrapper').trigger('click')
        await flushPromises()
        const labels = [...document.querySelectorAll('.el-tree-node__content')].map(node => node.textContent)
        expect(labels.filter(label => label === '唯一父分类')).toHaveLength(1)
        await document.querySelector<HTMLElement>('.el-tree-node__expand-icon')?.click()
        await flushPromises()
        const childLabels = [...document.querySelectorAll('.el-tree-node__content')].map(node => node.textContent)
        expect(childLabels.filter(label => label === '唯一子分类')).toHaveLength(1)
        const categories = [{ id: 1, name: '前沿科技', children: [] }, { id: 2, name: '测试分类427897', children: [] }]
        await wrapper.setProps({ categoryOptions: categories })
        await wrapper.setProps({ categoryOptions: categories.map(category => ({ ...category })) })
        await flushPromises()
        const refreshed = [...document.querySelectorAll('.el-tree-node__content')].map(node => node.textContent)
        expect(refreshed.filter(label => label === '前沿科技')).toHaveLength(1)
        expect(refreshed.filter(label => label === '测试分类427897')).toHaveLength(1)

    } finally {
        wrapper.unmount()
    }
})
