import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { getAuthRevision, invalidateAuthRequests } from '@/utils/request-context'

import { streamGenerate } from '../ai-studio'

const state = vi.hoisted(() => ({ token: '' as string | null, clearAuth: vi.fn(), push: vi.fn() }))
vi.mock('@/utils/auth', () => ({ getToken: () => state.token, clearAuthInfo: state.clearAuth }))
vi.mock('@/utils/cache', () => ({
    default: { get: () => state.token, getKey: (key: string) => key }
}))
vi.mock('@/router', () => ({
    default: { currentRoute: { value: { path: '/home' } }, push: state.push }
}))
vi.mock('@/utils/request', () => ({ myRequest: {} }))

const payload = { instruction: 'create', tables: [], gen_type: 'crud' }
const token = (sid: string, generation = 1) =>
    `header.${btoa(JSON.stringify({ scope: 'admin', data: { admin_id: 1, sid }, jti: generation }))}.signature`
const callbacks = () => ({ onChunk: vi.fn(), onDone: vi.fn(), onError: vi.fn() })
const unauthorized = () => ({
    headers: { get: () => 'application/json' },
    status: 200,
    json: async () => ({ code: 401 })
})

beforeEach(() => {
    state.token = token('session-a')
    vi.clearAllMocks()
    invalidateAuthRequests()
})
afterEach(() => vi.unstubAllGlobals())

describe('AI 流式请求的会话隔离', () => {
    it('当前会话正常接收流片段和完成事件', async () => {
        const read = vi
            .fn()
            .mockResolvedValueOnce({
                done: false,
                value: new TextEncoder().encode(
                    'event: chunk\ndata: {"content":"ok"}\n\nevent: done\ndata: {"stage_id":"stage"}\n\n'
                )
            })
            .mockResolvedValueOnce({ done: true })
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => ({
                headers: { get: () => 'text/event-stream' },
                ok: true,
                body: { getReader: () => ({ read, cancel: vi.fn() }) }
            }))
        )
        const handlers = callbacks()
        await streamGenerate(payload, handlers)
        expect(handlers.onChunk).toHaveBeenCalledWith('ok')
        expect(handlers.onDone).toHaveBeenCalledWith({ stage_id: 'stage' })
        expect(handlers.onError).not.toHaveBeenCalled()
    })

    it('切换登录后旧 401 不会清除新登录', async () => {
        let resolve!: (response: ReturnType<typeof unauthorized>) => void
        const fetchMock = vi.fn(
            (_url: string, _config: RequestInit) =>
                new Promise<ReturnType<typeof unauthorized>>((accept) => {
                    resolve = accept
                })
        )
        vi.stubGlobal('fetch', fetchMock)
        const handlers = callbacks()
        const result = streamGenerate(payload, handlers)
        state.token = token('session-b')
        invalidateAuthRequests()
        expect(fetchMock.mock.calls[0][1].signal?.aborted).toBe(true)
        resolve(unauthorized())
        await result
        expect(state.clearAuth).not.toHaveBeenCalled()
        expect(state.push).not.toHaveBeenCalled()
        expect(handlers.onError).not.toHaveBeenCalled()
    })

    it('同一会话刷新后旧 401 不会清除新 token', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => {
                state.token = token('session-a', 2)
                return unauthorized()
            })
        )
        const revision = getAuthRevision()
        await streamGenerate(payload, callbacks())
        expect(getAuthRevision()).toBe(revision)
        expect(state.clearAuth).not.toHaveBeenCalled()
    })

    it('当前 token 的 401 仍清理登录', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => unauthorized())
        )
        const handlers = callbacks()
        await streamGenerate(payload, handlers)
        expect(state.clearAuth).toHaveBeenCalledOnce()
        expect(state.push).toHaveBeenCalledOnce()
        expect(handlers.onError).toHaveBeenCalledOnce()
    })

    it('流读取期间切换账号，旧数据不会进入回调', async () => {
        const cancel = vi.fn()
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => ({
                headers: { get: () => 'text/event-stream' },
                ok: true,
                body: {
                    getReader: () => ({
                        cancel,
                        read: async () => {
                            state.token = token('session-b')
                            invalidateAuthRequests()
                            return {
                                done: false,
                                value: new TextEncoder().encode(
                                    'event: chunk\ndata: {"content":"private"}\n\n'
                                )
                            }
                        }
                    })
                }
            }))
        )
        const handlers = callbacks()
        await streamGenerate(payload, handlers)
        expect(cancel).toHaveBeenCalledOnce()
        expect(handlers.onChunk).not.toHaveBeenCalled()
        expect(handlers.onDone).not.toHaveBeenCalled()
        expect(handlers.onError).not.toHaveBeenCalled()
    })
})
