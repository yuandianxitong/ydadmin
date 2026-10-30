import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { expect, it, vi } from 'vitest'
import { createI18n } from 'vue-i18n'

import zhCN from '@/locales/zh-CN'

import UserList from './index.vue'

const mocks = vi.hoisted(() => ({ list: vi.fn(), import: vi.fn() }))
vi.mock('@/api/user', () => ({
    userManageApi: { getList: mocks.list, getUserList: mocks.list, importUsers: mocks.import }
}))
vi.mock('@/api/app', () => ({ getConfig: vi.fn() }))

it('keeps the user import entry connected to the CSV API and displays the real result once', async () => {
    mocks.list.mockResolvedValue({
        data: { list: [], pagination: { total: 0, current_page: 1, per_page: 20 } }
    })
    let complete!: (value: unknown) => void
    mocks.import.mockImplementation(
        () =>
            new Promise((resolve) => {
                complete = resolve
            })
    )
    const wrapper = mount(UserList, {
        global: {
            plugins: [
                createPinia(),
                createI18n({ legacy: false, locale: 'zh-CN', messages: { 'zh-CN': zhCN } })
            ],
            stubs: { teleport: true, ElSelect: true },
            directives: { hasPerm: {} }
        }
    })
    try {
        await flushPromises()
        await wrapper.find('.import-data .dialog__trigger button').trigger('click')
        await flushPromises()
        const input = wrapper.find('.import-data input[type="file"]')
        const file = new File(['mobile,nickname\n13800000000,测试用户'], 'users.csv', {
            type: 'text/csv'
        })
        Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
        await input.trigger('change')
        await flushPromises()
        const confirm = wrapper.findAll('.import-data .dialog-footer button').at(-1)!
        await confirm.trigger('click')
        await confirm.trigger('click')
        expect(mocks.import).toHaveBeenCalledTimes(1)
        expect(mocks.import).toHaveBeenCalledWith(file)
        complete({
            data: { success_count: 1, fail_count: 1, errors: [{ row: 3, message: '手机号无效' }] }
        })
        await flushPromises()
        expect(wrapper.find('.imp-done-stats').text()).toContain('1成功导入')
        expect(wrapper.find('.imp-errors').text()).toContain('3: 手机号无效')
        expect(mocks.list.mock.calls.length).toBeGreaterThan(1)
    } finally {
        wrapper.unmount()
    }
})
