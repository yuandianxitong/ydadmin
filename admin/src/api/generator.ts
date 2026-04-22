import type { GeneratorColumnInfo, GeneratorConfig, GeneratorPreviewResult, GeneratorTableInfo } from '@/types/generator'
import { myRequest } from '@/utils/request'

export const generatorApi = {
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
            route?: string
        }>('/adminapi/system/generator/generate', data)
    }
}
