import { flushPromises, mount } from '@vue/test-utils'
import { ElPagination } from 'element-plus'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { h, nextTick } from 'vue'

import ColumnConfig from '@/components/ColumnConfig/index.vue'

import ProTable from './index.vue'

const rows = [{ id: 7, name: '示例行' }]

beforeEach(() => {
    const data = new Map<string, string>()
    vi.stubGlobal('localStorage', {
        getItem: (key: string) => data.get(key) ?? null,
        setItem: (key: string, value: string) => data.set(key, value),
        removeItem: (key: string) => data.delete(key)
    })
})
afterEach(() => vi.unstubAllGlobals())

describe('ProTable presentation compatibility', () => {
    it('keeps page selection callbacks and never invents a delete operation', async () => {
        const selection = vi.fn()
        const wrapper = mount(ProTable, {
            props: {
                title: '列表',
                storageKey: 'selection-test',
                data: rows,
                selection: true,
                columns: [{ key: 'name', label: '名称' }],
                onSelectionChange: selection
            },
            slots: { batchActions: () => h('button', '自定义批量操作') }
        })
        try {
            await flushPromises()
            await wrapper.find('.el-table__body input[type="checkbox"]').setValue(true)
            expect(selection).toHaveBeenLastCalledWith(rows)
            expect(wrapper.find('.table-sel-info').text()).toContain('已选 1 项')
            expect(wrapper.text()).toContain('自定义批量操作')
            expect(wrapper.text()).not.toContain('批量删除')
            await wrapper.find('.table-sel-clear').trigger('click')
            expect(selection).toHaveBeenLastCalledWith([])
            expect(wrapper.find('.table-sel-info').exists()).toBe(false)
        } finally {
            wrapper.unmount()
        }
    })

    it('preserves existing pagination handlers and custom row rendering', async () => {
        const sizeChange = vi.fn()
        const wrapper = mount(ProTable, {
            props: {
                title: '列表',
                storageKey: 'pagination-test',
                data: rows,
                pagination: { page: 1, limit: 20, total: 80 },
                columns: [{ key: 'name', label: '名称' }]
            },
            slots: {
                name: ({ row }) => h('strong', String(row.name)),
                pagination: () =>
                    h(ElPagination, { total: 80, pageSize: 20, onSizeChange: sizeChange })
            }
        })
        try {
            await flushPromises()
            expect(wrapper.find('.el-table__body strong').text()).toBe('示例行')
            expect(wrapper.findAll('strong')).toHaveLength(1)
            expect(wrapper.find('.table-footer-total').text()).toBe('共 80 条记录')
            expect(wrapper.findAllComponents(ElPagination)).toHaveLength(1)
            wrapper.findComponent(ElPagination).vm.$emit('size-change', 50)
            expect(sizeChange).toHaveBeenCalledWith(50)
        } finally {
            wrapper.unmount()
        }
    })

    it('keeps required columns visible and applies configuration even without writable storage', async () => {
        localStorage.setItem('col-cfg:columns-test', '{invalid-json')
        const wrapper = mount(ProTable, {
            props: {
                title: '列表',
                storageKey: 'columns-test',
                data: rows,
                columns: [
                    { key: 'id', label: 'ID', required: true },
                    { key: 'name', label: '名称' }
                ]
            }
        })
        try {
            await flushPromises()
            vi.spyOn(localStorage, 'setItem').mockImplementation(() => {
                throw new Error('storage full')
            })
            wrapper.findComponent(ColumnConfig).vm.$emit('update:columns', [
                { key: 'id', label: 'ID', required: true, visible: false },
                { key: 'name', label: '名称', visible: false }
            ])
            await nextTick()
            const labels = wrapper.findAll('th .cell').map((cell) => cell.text())
            expect(labels).toContain('ID')
            expect(labels).not.toContain('名称')
        } finally {
            wrapper.unmount()
        }
    })
})
