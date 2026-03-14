import { http } from '@/utils/request'

export interface VersionCheckResult {
  need_update: boolean
  force_update?: boolean
  version?: string
  version_code?: number
  download_url?: string
  description?: string
}

export const versionApi = {
  checkUpdate: (platform: string, versionCode: number) =>
    http.get<VersionCheckResult>('/api/version/check', { platform, version_code: versionCode }),
}
