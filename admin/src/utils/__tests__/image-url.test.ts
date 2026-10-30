import { describe, expect, it } from 'vitest'

import { resolveImageUrl } from '../image-url'

const production = {
    development: false,
    origin: 'https://admin.example.test',
    storageDomain: 'https://cdn.example.test/',
    siteUrl: 'http://localhost'
}

describe('admin image display URLs', () => {
    it('loads server static assets and local uploads from the current server, ignoring stale site/OSS domains', () => {
        for (const path of ['/static/diy/home/banner.jpg', '/storage/uploads/image.png']) {
            expect(resolveImageUrl(path, production)).toBe(`${production.origin}${path}`)
        }
    })

    it('uses the configured API server for cross-origin deployments and the proxy in development', () => {
        const path = '/static/diy/home/banner.jpg'
        const options = { ...production, apiBaseUrl: 'https://api.example.test/' }
        expect(resolveImageUrl(path, options)).toBe(`https://api.example.test${path}`)
        expect(resolveImageUrl(path.slice(1), { ...options, development: true })).toBe(path)
    })

    it('repairs legacy loopback static/storage addresses while preserving query and hash', () => {
        expect(
            resolveImageUrl(
                'http://127.0.0.1:8000/static/diy/home/banner.jpg?v=2#preview',
                production
            )
        ).toBe('https://admin.example.test/static/diy/home/banner.jpg?v=2#preview')
        expect(
            resolveImageUrl('http://localhost/storage/image.png', {
                ...production,
                development: true
            })
        ).toBe('/storage/image.png')
    })

    it('keeps complete external, data, blob and protocol-relative URLs unchanged', () => {
        for (const value of [
            'https://other.example.test/a.png?signature=x',
            '//cdn.example.test/a.png',
            'data:image/png;base64,abc',
            'blob:https://admin.example.test/id',
            ''
        ]) {
            expect(resolveImageUrl(value, production)).toBe(value)
        }
    })

    it('keeps cloud object keys on the configured CDN and joins slashes correctly', () => {
        expect(resolveImageUrl('uploads/cloud.png', production)).toBe(
            'https://cdn.example.test/uploads/cloud.png'
        )
    })

    it('maps built-in UniApp tab icons to existing server files only for display', () => {
        expect(resolveImageUrl('/static/tabbar/home-active.png', production)).toBe(
            'https://admin.example.test/static/diy/tabbar/home-active.png'
        )
        expect(resolveImageUrl('/static/tabbar/custom.png', production)).toBe(
            'https://admin.example.test/static/tabbar/custom.png'
        )
    })
})
