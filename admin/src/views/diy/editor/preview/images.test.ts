import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import type { Component } from 'vue'

import { useAppStore } from '@/store/modules/app.store'

import { componentStyleToCss } from '../styleUtils'
import Banner from './Banner.vue'
import CategoryNav from './CategoryNav.vue'
import FloatButton from './FloatButton.vue'
import ImageAd from './ImageAd.vue'
import ImageCube from './ImageCube.vue'
import NavGrid from './NavGrid.vue'
import Notice from './Notice.vue'
import ServiceMenu from './ServiceMenu.vue'
import Video from './Video.vue'

vi.mock('@/api/app', () => ({ getConfig: vi.fn() }))

const path = '/static/diy/home/banner.jpg'
const cases: [string, Component, Record<string, unknown>][] = [
    ['banner', Banner, { items: [{ image: path }] }],
    ['category', CategoryNav, { items: [{ title: '分类', icon: path }] }],
    ['image ad', ImageAd, { items: [{ image: path }] }],
    ['image cube', ImageCube, { items: [{ image: path }] }],
    ['navigation', NavGrid, { items: [{ text: '导航', icon: path }] }],
    ['floating button', FloatButton, { items: [{ icon: path }] }],
    ['notice', Notice, { icon: path }],
    ['service menu', ServiceMenu, { items: [{ text: '服务', icon: path }] }],
    ['video poster', Video, { src: '/storage/video.mp4', poster: path }]
]

beforeEach(() => {
    setActivePinia(createPinia())
    vi.stubEnv('DEV', false)
    vi.stubEnv('VITE_APP_API_URL', 'https://api.example.test')
    useAppStore().config = {
        site_url: 'http://localhost',
        storage_oss_domain: 'https://wrong-cdn.example.test'
    }
})
afterEach(() => vi.unstubAllEnvs())

it.each(cases)(
    'resolves %s images through the server without changing saved properties',
    (_name, component, config) => {
        const original = JSON.stringify(config)
        const wrapper = mount(component, { props: { props: config } })
        try {
            expect(wrapper.find('img').attributes('src')).toBe(`https://api.example.test${path}`)
            expect(JSON.stringify(config)).toBe(original)
        } finally {
            wrapper.unmount()
        }
    }
)

it('resolves decoration background images through the same server', () => {
    const store = useAppStore()
    const css = componentStyleToCss({ background: { type: 'image', image: path } }, (url) =>
        store.getImageUrl(url)
    )
    expect(css.backgroundImage).toBe(`url("https://api.example.test${path}")`)
})
