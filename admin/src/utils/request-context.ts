import { TOKEN_KEY } from '@/constants/cache'

import cache from './cache'

const controllers = new Set<AbortController>()
export const pendingGetRequests = new Map<string, Promise<unknown>>()
let revision = 0
let identity = readIdentity()

function readIdentity(): string {
    const token = cache.get(TOKEN_KEY) as string | null
    if (!token) return ''
    try {
        const encoded = token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/')
        const claims = JSON.parse(atob(encoded))
        const data = claims.data
        if (typeof data?.sid === 'string' && data.sid !== '') {
            // 刷新沿用 sid，同一个会话的 token 更换不取消其它有效请求。
            return `${claims.scope}:${data.admin_id}:${data.sid}`
        }
    } catch {
        // 兼容没有 sid 的旧 token；原始 token 仅在模块内比较，不进入去重 key。
    }
    return token
}

export function invalidateAuthRequests(): void {
    identity = readIdentity()
    revision++
    pendingGetRequests.clear()
    for (const controller of controllers) controller.abort()
    controllers.clear()
}

export function getAuthRevision(): number {
    if (readIdentity() !== identity) invalidateAuthRequests()
    return revision
}

export function trackRequest(controller: AbortController): () => void {
    controllers.add(controller)
    return () => controllers.delete(controller)
}

if (typeof window !== 'undefined') {
    window.addEventListener('storage', (event) => {
        if (event.key === null || event.key === cache.getKey(TOKEN_KEY)) getAuthRevision()
    })
}
