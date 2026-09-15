import { describe, expect, it } from 'vitest'

import { summarizeUserAgent } from '../user-agent'

describe('summarizeUserAgent', () => {
    it('summarizes Chrome on macOS', () => {
        const ua =
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36'
        expect(summarizeUserAgent(ua)).toBe('Chrome 128 / macOS')
    })

    it('prefers Edge over the Chrome token it also carries', () => {
        const ua =
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36 Edg/127.0.2651.74'
        expect(summarizeUserAgent(ua)).toBe('Edge 127 / Windows')
    })

    it('summarizes Firefox on Linux', () => {
        const ua = 'Mozilla/5.0 (X11; Linux x86_64; rv:129.0) Gecko/20100101 Firefox/129.0'
        expect(summarizeUserAgent(ua)).toBe('Firefox 129 / Linux')
    })

    it('treats iPhone as iOS even though the UA says "like Mac OS X"', () => {
        const ua =
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1'
        expect(summarizeUserAgent(ua)).toBe('Safari 17 / iOS')
    })

    it('treats Android as Android even though the UA says Linux', () => {
        const ua =
            'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36'
        expect(summarizeUserAgent(ua)).toBe('Chrome 126 / Android')
    })

    it('returns a dash for an empty UA', () => {
        expect(summarizeUserAgent('')).toBe('-')
        expect(summarizeUserAgent('   ')).toBe('-')
    })

    it('falls back to the first 40 characters when nothing is recognised', () => {
        expect(summarizeUserAgent('curl/8.7.1')).toBe('curl/8.7.1')
        expect(summarizeUserAgent('x'.repeat(60))).toBe('x'.repeat(40))
    })
})
