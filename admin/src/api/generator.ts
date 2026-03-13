import type { GeneratorColumnInfo, GeneratorConfig, GeneratorTableInfo } from '@/types/api'
import { myRequest } from '@/utils/request'

export interface GeneratorPreviewResult {
    [filename: string]: string
}

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
        return myRequest.post<{ files: string[] }>('/adminapi/system/generator/generate', data)
    }
}
