import { flushPromises, mount } from '@vue/test-utils'
import { expect, it } from 'vitest'

import CronBuilder from './index.vue'

it('preserves an existing cron range expression when the Pro builder opens', async () => {
    const expression = '*/10 8-20 * * 1-5'
    const wrapper = mount(CronBuilder, { props: { modelValue: expression } })
    try {
        await flushPromises()
        const input = wrapper.find('.cron-builder__params input')
        expect((input.element as HTMLInputElement).value).toBe(expression)
        expect(wrapper.emitted('update:modelValue')?.flat()).not.toContain('* * * * *')
        await input.setValue('*/15 8-20 * * 1-5')
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['*/15 8-20 * * 1-5'])
    } finally {
        wrapper.unmount()
    }
})
