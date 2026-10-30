import { flushPromises, mount } from '@vue/test-utils'
import { expect, it, vi } from 'vitest'
import { createI18n } from 'vue-i18n'

import zhCN from '@/locales/zh-CN'
import type { RoleInfo } from '@/types/system'

import AssignPermissionsDialog from './AssignPermissionsDialog.vue'

const mocks = vi.hoisted(() => ({ assign: vi.fn() }))
vi.mock('@/api/role', () => ({
    roleApi: {
        getMenuTree: async () => ({
            data: [
                {
                    id: 1,
                    title: '系统',
                    type: 1,
                    children: [
                        {
                            id: 2,
                            title: '角色',
                            type: 2,
                            children: [
                                {
                                    id: 3,
                                    title: '编辑角色',
                                    type: 3,
                                    permission: 'system.role.update'
                                }
                            ]
                        }
                    ]
                }
            ]
        }),
        getRolePermissions: async () => ({ data: { menu_ids: [3] } }),
        assignPermissions: mocks.assign
    }
}))

it('keeps the menu assignment payload and shows the current data scope without silently editing it', async () => {
    mocks.assign.mockResolvedValue({ code: 200 })
    const wrapper = mount(AssignPermissionsDialog, {
        props: {
            modelValue: true,
            roleInfo: { id: 7, name: 'editor', title: '编辑', status: 1, data_scope: 5 } as RoleInfo
        },
        global: {
            plugins: [createI18n({ legacy: false, locale: 'zh-CN', messages: { 'zh-CN': zhCN } })],
            stubs: { teleport: true }
        }
    })
    try {
        await flushPromises()
        await wrapper.findAll('.rp-tab-n')[1].trigger('click')
        const scopes = wrapper.findAll('.rp-scope-rd')
        expect(scopes).toHaveLength(5)
        expect((scopes[4].element as HTMLInputElement).checked).toBe(true)
        expect(scopes.every((scope) => (scope.element as HTMLInputElement).disabled)).toBe(true)
        await wrapper.findAll('.rp-tab-n')[0].trigger('click')
        await wrapper
            .findAll('.rp-link')
            .find((button) => button.text() === '全选')!
            .trigger('click')
        expect(wrapper.find('.rp-foot-info').text()).toContain('已选 3 项')
        await wrapper
            .findAll('button')
            .find((button) => button.text() === '保存')!
            .trigger('click')
        await flushPromises()
        expect(mocks.assign).toHaveBeenCalledWith(7, { menu_ids: [1, 2, 3] })
        expect(wrapper.emitted('success')).toHaveLength(1)
    } finally {
        wrapper.unmount()
    }
})
