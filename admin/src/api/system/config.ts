/* cspell:disable */
import type {
    AuthInfoRes,
    ConfigBatchUpdateItem,
    ConfigInfo,
    ConfigReq
} from '@/types/api'
import { myRequest } from '@/utils/request'

// 获取配置分组
export function getConfigGroups() {
    return myRequest.get<Record<string, string>>('/adminapi/system/config/groups')
}

// 获取指定分组的配置列表
export function getConfigsByGroup(group: string = 'basic') {
    return myRequest.get<ConfigInfo[]>(`/adminapi/system/config?group=${group}`)
}

// 获取单个配置
export function getConfig(id: number) {
    return myRequest.get<ConfigInfo>(`/adminapi/system/config/${id}`)
}

// 更新单个配置
export function updateConfig(id: number, data: Partial<ConfigReq>) {
    return myRequest.put<void>(`/adminapi/system/config/${id}`, data)
}

// 批量更新配置
export function batchUpdateConfigs(configs: ConfigBatchUpdateItem[]) {
    return myRequest.post<void>('/adminapi/system/config/batch-update', { configs })
}

// 获取全局配置
export function getGlobalConfigs() {
    return myRequest.get<AuthInfoRes>('/adminapi/system/config/global')
}
