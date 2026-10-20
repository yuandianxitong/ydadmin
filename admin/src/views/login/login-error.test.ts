import { flushPromises, mount } from '@vue/test-utils'
import { expect, it, vi } from 'vitest'
import { createI18n } from 'vue-i18n'

import Login from './index.vue'

const mocks = vi.hoisted(() => ({ login: vi.fn(), captcha: vi.fn() }))
vi.mock('@/store/modules/user.store', () => ({ default: () => ({ login: mocks.login }) }))
vi.mock('@/api/auth', () => ({ authApi: { getCaptcha: mocks.captcha } }))
vi.mock('vue-router', () => ({ useRouter: () => ({ push: vi.fn() }), useRoute: () => ({ query: {} }) }))

it('keeps a visible captcha error after requesting a new captcha', async () => {
    mocks.captcha.mockResolvedValue({ data: { key: 'captcha-key', image: 'data:image/png;base64,AA==' } })
    mocks.login.mockRejectedValue(Object.assign(new Error('Request failed with status code 400'), {
        response: { data: { message: '验证码错误' } }
    }))
    const wrapper = mount(Login, {
        global: { plugins: [createI18n({ legacy: false, locale: 'zh-CN', missingWarn: false, fallbackWarn: false })] }
    })
    try {
        await flushPromises()
        const vm = wrapper.vm as unknown as {
            loginForm: { username: string; password: string; captcha: string }
            handleLogin: () => Promise<void>
        }
        Object.assign(vm.loginForm, { username: 'admin', password: 'Admin123!', captcha: '0000' })
        await vm.handleLogin()
        await flushPromises()
        expect(mocks.login).toHaveBeenCalledOnce()
        expect(wrapper.find('.el-alert').text()).toContain('验证码错误')
        expect(mocks.captcha).toHaveBeenCalledTimes(2)
    } finally {
        wrapper.unmount()
    }
})
