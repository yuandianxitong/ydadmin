import { describe, expect, it, vi } from 'vitest'

import { createForceLogoutHandler, type ForceLogoutDeps } from '../forceLogout'

function makeDeps(overrides: Partial<ForceLogoutDeps> = {}) {
    const calls: string[] = []
    const deps: ForceLogoutDeps = {
        alert: vi.fn(async (message: string, title: string) => {
            calls.push(`alert:${title}:${message}`)
        }),
        clearAuth: vi.fn(() => {
            calls.push('clear')
        }),
        goLogin: vi.fn(() => {
            calls.push('login')
        }),
        t: (key: string) => `[${key}]`,
        ...overrides
    }
    return { deps, calls }
}

describe('createForceLogoutHandler', () => {
    it('alerts the revoked message, then clears auth, then goes to login', async () => {
        const { deps, calls } = makeDeps()
        await createForceLogoutHandler(deps)({ reason: 'revoked', message: '' })

        expect(calls).toEqual([
            'alert:[realtime.forceLogoutTitle]:[realtime.revoked]',
            'clear',
            'login'
        ])
    })

    it('uses the kicked message when an administrator kicked the session', async () => {
        const { deps, calls } = makeDeps()
        await createForceLogoutHandler(deps)({ reason: 'kicked', message: 'bye' })

        expect(calls[0]).toBe('alert:[realtime.forceLogoutTitle]:[realtime.kicked]')
    })

    it('ignores a second event while the first one is still being handled', async () => {
        let release: () => void = () => {}
        const { deps } = makeDeps({
            alert: vi.fn(
                () =>
                    new Promise<void>((resolve) => {
                        release = resolve
                    })
            )
        })
        const handle = createForceLogoutHandler(deps)

        const first = handle({ reason: 'kicked' })
        await handle({ reason: 'revoked' })
        release()
        await first

        expect(deps.alert).toHaveBeenCalledTimes(1)
        expect(deps.clearAuth).toHaveBeenCalledTimes(1)
        expect(deps.goLogin).toHaveBeenCalledTimes(1)
    })

    it('still signs out when the alert is dismissed or fails', async () => {
        const { deps } = makeDeps({ alert: vi.fn().mockRejectedValue('cancel') })
        await createForceLogoutHandler(deps)()

        expect(deps.clearAuth).toHaveBeenCalledTimes(1)
        expect(deps.goLogin).toHaveBeenCalledTimes(1)
    })
})
