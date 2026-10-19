import { afterEach, beforeEach, expect, it, vi } from 'vitest'

import { fetchOpenApiDocument } from '../api-doc'

const state = vi.hoisted(() => ({ token: 'admin-token', revision: 1 }))
vi.mock('@/utils/auth', () => ({ getToken: () => state.token }))
vi.mock('@/utils/request-context', () => ({ getAuthRevision: () => state.revision }))
beforeEach(() => {
    state.token = 'admin-token'
    state.revision = 1
})
afterEach(() => vi.unstubAllGlobals())

it('fetches raw OpenAPI JSON with bearer authentication', async () => {
    const document = { openapi: '3.0.3', paths: {} }
    const fetcher = vi.fn().mockResolvedValue({ ok: true, json: async () => document })
    vi.stubGlobal('fetch', fetcher)
    expect(await fetchOpenApiDocument('admin')).toEqual(document)
    const [url, options] = fetcher.mock.calls[0]
    expect(String(url)).toContain('/adminapi/system/api-doc/openapi.json?type=admin')
    expect(String(url)).not.toContain('admin-token')
    expect(options.headers.Authorization).toBe('Bearer admin-token')
})

it('rejects business permission errors even when HTTP status is 200', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({ code: 403, message: 'Permission denied' })
        })
    )
    await expect(fetchOpenApiDocument('api')).rejects.toThrow('Permission denied')
})

it('discards a document if the admin session changed while it was loading', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue({
            ok: true,
            json: async () => {
                state.revision++
                return { openapi: '3.0.3', paths: {} }
            }
        })
    )
    await expect(fetchOpenApiDocument('admin')).rejects.toMatchObject({ name: 'AbortError' })
})
