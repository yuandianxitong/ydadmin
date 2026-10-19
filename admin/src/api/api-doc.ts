import { getToken } from '@/utils/auth'
import { getAuthRevision } from '@/utils/request-context'

export interface OpenApiDocument {
    openapi: string
    paths: Record<string, unknown>
    [key: string]: unknown
}

/** OpenAPI 是裸 JSON，独立读取，避免套用业务响应信封。Token 只通过请求头传递。 */
export async function fetchOpenApiDocument(
    type: 'admin' | 'api',
    signal?: AbortSignal
): Promise<OpenApiDocument> {
    const token = getToken()
    const revision = getAuthRevision()
    const base = import.meta.env.DEV
        ? window.location.origin
        : import.meta.env.VITE_APP_API_URL || window.location.origin
    const response = await fetch(
        new URL(`/adminapi/system/api-doc/openapi.json?type=${type}`, base),
        {
            headers: {
                Accept: 'application/json',
                ...(token ? { Authorization: `Bearer ${token}` } : {})
            },
            signal
        }
    )
    const data = await response.json()
    if (signal?.aborted || revision !== getAuthRevision()) {
        throw new DOMException('Request cancelled', 'AbortError')
    }
    if (!response.ok || typeof data?.openapi !== 'string' || !data?.paths) {
        throw new Error(data?.message || `HTTP ${response.status}`)
    }
    return data
}
