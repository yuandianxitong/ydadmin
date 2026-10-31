import type {
    GeneratorCapabilities,
    GeneratorColumnInfo,
    GeneratorConfig,
    GeneratorNextSteps,
    GeneratorPreviewResult,
    GeneratorTableInfo
} from '@/types/generator'
import { myRequest } from '@/utils/request'

export const generatorApi = {
    getCapabilities() {
        return myRequest.get<GeneratorCapabilities>('/adminapi/system/generator/capabilities')
    },
    getTables() {
        return myRequest.get<GeneratorTableInfo[]>('/adminapi/system/generator/tables')
    },
    getColumns(table: string) {
        return myRequest.get<GeneratorColumnInfo[]>('/adminapi/system/generator/columns', {
            params: { table }
        })
    },
    preview(data: GeneratorConfig) {
        return myRequest.post<GeneratorPreviewResult>('/adminapi/system/generator/preview', data)
    },
    generate(data: GeneratorConfig) {
        return myRequest.post<{
            files: Array<{ path: string; status: string; reason?: string }>
            next_steps: GeneratorNextSteps
        }>('/adminapi/system/generator/generate', data)
    }
}
