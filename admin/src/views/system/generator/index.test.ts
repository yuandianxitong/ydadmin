import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, expect, it, vi } from 'vitest'
import { defineComponent, h, watch } from 'vue'
import { createI18n } from 'vue-i18n'

import zhCN from '@/locales/zh-CN'

import Generator from './index.vue'

const api = vi.hoisted(() => ({
    getTables: vi.fn(),
    getCapabilities: vi.fn(),
    getColumns: vi.fn(),
    preview: vi.fn(),
    generate: vi.fn()
}))
vi.mock('@/api/generator', () => ({ generatorApi: api }))
vi.mock('@/components/ProTable/index.vue', async () => {
    const { defineComponent, h } = await import('vue')
    return {
        default: defineComponent({
            name: 'GeneratorTableFixture',
            props: ['data'],
            setup(p, { slots }) {
                return () =>
                    h('div', [
                        slots.headerExtra?.(),
                        ...(p.data || []).map((row: unknown) => slots.action?.({ row }))
                    ])
            }
        })
    }
})
const Pass = defineComponent({
    setup(_, { slots }) {
        return () => h('div', slots.default?.())
    }
})
const Input = defineComponent({
    props: ['modelValue', 'placeholder'],
    emits: ['update:modelValue', 'input'],
    setup(p, { emit }) {
        return () =>
            h('input', {
                value: p.modelValue,
                placeholder: p.placeholder,
                onInput: (e: Event) => {
                    const v = (e.target as HTMLInputElement).value
                    emit('update:modelValue', v)
                    emit('input', v)
                }
            })
    }
})
const Button = defineComponent({
    props: ['disabled'],
    setup(p, { slots, attrs }) {
        return () => h('button', { ...attrs, disabled: p.disabled }, slots.default?.())
    }
})
const Dialog = defineComponent({
    props: ['modelValue', 'title'],
    emits: ['closed', 'close'],
    setup(p, { slots, emit }) {
        watch(
            () => p.modelValue,
            (value) => {
                if (!value) emit('closed')
            }
        )
        return () =>
            p.modelValue ? h('section', { 'data-dialog': p.title }, slots.default?.()) : null
    }
})

function render() {
    return mount(Generator, {
        global: {
            plugins: [createI18n({ legacy: false, locale: 'zh-CN', messages: { 'zh-CN': zhCN } })],
            stubs: {
                ElDialog: Dialog,
                ElInput: Input,
                ElButton: Button,
                ElCard: Pass,
                ElForm: Pass,
                ElFormItem: Pass,
                ElRow: Pass,
                ElCol: Pass,
                ElTable: Pass,
                ElTableColumn: true,
                ElSteps: true,
                ElStep: true,
                ElTabs: Pass,
                ElTabPane: Pass,
                ElTag: Pass,
                ElAlert: true,
                ElRadioGroup: Pass,
                ElRadioButton: Pass
            }
        }
    })
}
function button(w: ReturnType<typeof render>, text: string) {
    return w.findAll('button').find((b) => b.text() === text)!
}
beforeEach(() => {
    vi.clearAllMocks()
    api.getCapabilities.mockResolvedValue({
        data: {
            writes_enabled: true,
            table_prefix: '',
            reserved_modules: ['system', 'article'],
            scope: 'admin'
        }
    })
    api.getTables.mockResolvedValue({
        data: [
            { name: 'gen_articles', comment: '示例' },
            { name: 'biz_tickets', comment: '工单' }
        ]
    })
    api.getColumns.mockResolvedValue({ data: [] })
    api.preview.mockResolvedValue({
        data: {
            model: { path: 'server/app/model/demo/GenArticle.php', content: '<?php // preview' }
        }
    })
    api.generate.mockResolvedValue({
        data: {
            files: [
                { path: 'server/database/generated/demo-gen_article-menu.sql', status: 'created' }
            ],
            next_steps: {
                menu_sql: 'server/database/generated/demo-gen_article-menu.sql',
                route: '/admin/demo/gen-article',
                manual: [
                    'mysql --default-character-set=utf8mb4 -u USER -p DATABASE < server/database/generated/demo-gen_article-menu.sql',
                    '(cd server && php start.php reload)',
                    'pnpm --dir admin build'
                ],
                docker: []
            }
        }
    })
})
it('搜索注释、按当前表重置模型，成功后只显示一个结果弹窗及完整部署步骤', async () => {
    const w = render()
    await flushPromises()
    await w.find('input[placeholder="搜索表名或注释"]').setValue('工单')
    expect(w.findComponent({ name: 'GeneratorTableFixture' }).props('data')).toHaveLength(1)
    await w.find('input[placeholder="搜索表名或注释"]').setValue('')
    await button(w, '编辑配置').trigger('click')
    await flushPromises()
    expect(w.find('input[placeholder="如 Article, Product"]').element).toHaveProperty(
        'value',
        'GenArticle'
    )
    await w.find('input[placeholder="如 demo、biz"]').setValue('demo')
    await button(w, '预览代码').trigger('click')
    await flushPromises()
    await button(w, '确认生成').trigger('click')
    await flushPromises()
    expect(api.generate).toHaveBeenCalledTimes(1)
    expect(w.findAll('[data-dialog]')).toHaveLength(1)
    expect(w.text()).toContain('--default-character-set=utf8mb4')
    expect(w.text()).toContain('pnpm --dir admin build')
    expect(w.text()).toContain('普通角色')
    w.unmount()
})
it('APP_DEBUG=false 时保留表配置但禁止预览', async () => {
    api.getCapabilities.mockResolvedValue({
        data: { writes_enabled: false, table_prefix: '', reserved_modules: [], scope: 'admin' }
    })
    const w = render()
    await flushPromises()
    await button(w, '编辑配置').trigger('click')
    await flushPromises()
    expect(button(w, '预览代码').attributes('disabled')).toBeDefined()
    expect(api.preview).not.toHaveBeenCalled()
    w.unmount()
})

it('切换数据表后不会沿用上一张表的模型或模块', async () => {
    const w = render()
    await flushPromises()
    await button(w, '编辑配置').trigger('click')
    await flushPromises()
    await w.find('input[placeholder="如 demo、biz"]').setValue('demo')
    w.findComponent(Dialog).vm.$emit('close')
    await flushPromises()
    await w
        .findAll('button')
        .filter((b) => b.text() === '编辑配置')[1]
        .trigger('click')
    await flushPromises()
    expect(w.find('input[placeholder="如 Article, Product"]').element).toHaveProperty(
        'value',
        'BizTicket'
    )
    expect(w.find('input[placeholder="如 demo、biz"]').element).toHaveProperty('value', '')
    w.unmount()
})
