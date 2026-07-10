import { PageEnum } from '@/constants/page'
import router from '@/router'
import { clearAuthInfo, getToken } from '@/utils/auth'
import { myRequest } from '@/utils/request'

export interface StageFile {
    path: string
    lines: number
    exists: boolean
}

export interface StreamDoneData {
    stage_id: string
    generation_id: string
    files: StageFile[]
    skipped: string[]
}

export interface StreamCallbacks {
    onChunk: (text: string) => void
    onDone: (data: StreamDoneData) => void
    onError: (message: string) => void
}

/** SSE 流式生成：myRequest 不支持 ReadableStream，用原生 fetch */
export async function streamGenerate(
    payload: { instruction: string; tables: string[]; gen_type: string },
    callbacks: StreamCallbacks,
    signal?: AbortSignal
): Promise<void> {
    // 开发环境：留空，通过 vite proxy 代理；生产环境：读取 VITE_APP_API_URL（对齐 utils/request.ts baseURL 逻辑）
    const base = import.meta.env.DEV ? '' : import.meta.env.VITE_APP_API_URL || ''
    const resp = await fetch(`${base}/adminapi/system/ai-studio/stream`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Authorization: `Bearer ${getToken()}`
        },
        body: JSON.stringify(payload),
        signal
    })
    // admin 后端鉴权失败为 HTTP 200 + body {code:401}（core/http/Response 形态），
    // 需先看 Content-Type 判断是不是 SSE 流，不是则按普通 JSON 错误处理
    const contentType = resp.headers.get('Content-Type') || ''
    if (!contentType.includes('text/event-stream')) {
        let json: any = {}
        try {
            json = await resp.json()
        } catch {
            // ignore parse error, fall through to generic error handling
        }
        if (json.code === 401) {
            clearAuthInfo()
            if (router.currentRoute.value.path !== PageEnum.LOGIN) {
                router.push(PageEnum.LOGIN)
            }
            callbacks.onError('登录已过期，请重新登录')
            return
        }
        callbacks.onError(json.message || '请求失败')
        return
    }
    if (!resp.ok || !resp.body) {
        callbacks.onError(`请求失败（HTTP ${resp.status}）`)
        return
    }
    const reader = resp.body.getReader()
    const decoder = new TextDecoder()
    let buffer = ''
    let currentEvent = ''
    let receivedTerminal = false
    for (;;) {
        const { done, value } = await reader.read()
        if (done) break
        buffer += decoder.decode(value, { stream: true })
        const lines = buffer.split('\n')
        buffer = lines.pop() || ''
        for (const line of lines) {
            if (line.startsWith('event: ')) {
                currentEvent = line.slice(7).trim()
            } else if (line.startsWith('data: ')) {
                let data: any
                try {
                    data = JSON.parse(line.slice(6))
                } catch {
                    continue
                }
                if (currentEvent === 'chunk') callbacks.onChunk(data.content ?? '')
                else if (currentEvent === 'done') {
                    receivedTerminal = true
                    callbacks.onDone(data)
                } else if (currentEvent === 'error') {
                    receivedTerminal = true
                    callbacks.onError(data.message ?? '生成失败')
                }
            }
        }
    }
    if (!receivedTerminal && !signal?.aborted) {
        callbacks.onError('连接中断，请重试')
    }
}

export const aiStudioApi = {
    preview(stageId: string, path: string) {
        return myRequest.post<{ code: string }>('/adminapi/system/ai-studio/preview', {
            stage_id: stageId,
            path
        })
    },
    diff(stageId: string) {
        return myRequest.post<{ diff: string }>('/adminapi/system/ai-studio/diff', {
            stage_id: stageId
        })
    },
    apply(stageId: string, paths: string[]) {
        return myRequest.post<{ written: string[] }>('/adminapi/system/ai-studio/apply', {
            stage_id: stageId,
            paths
        })
    },
    feedback(generationId: string, action: 'accepted' | 'rejected') {
        return myRequest.post('/adminapi/system/ai-studio/feedback', {
            generation_id: generationId,
            action
        })
    }
}
