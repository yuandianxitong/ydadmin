import { getToken } from '@/utils/auth'
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
    const resp = await fetch('/adminapi/system/ai-studio/stream', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Authorization: `Bearer ${getToken()}`
        },
        body: JSON.stringify(payload),
        signal
    })
    if (resp.status === 401) {
        callbacks.onError('登录已过期，请重新登录')
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
                else if (currentEvent === 'done') callbacks.onDone(data)
                else if (currentEvent === 'error') callbacks.onError(data.message ?? '生成失败')
            }
        }
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
