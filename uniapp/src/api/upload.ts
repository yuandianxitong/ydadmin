import { getToken } from '@/utils/auth'

/**
 * 与 utils/request.ts 保持一致的 BASE_URL 解析策略：
 * H5 DEV 模式下使用相对路径，依赖 Vite 代理转发；其他平台直接使用配置的完整地址。
 */
let BASE_URL = import.meta.env.VITE_APP_API_URL || ''
// #ifdef H5
if (import.meta.env.DEV) BASE_URL = ''
// #endif

export const uploadApi = {
  uploadImage: (filePath: string): Promise<{ url: string; path: string }> => {
    return new Promise((resolve, reject) => {
      uni.uploadFile({
        url: `${BASE_URL}/api/common/upload/image`,
        filePath,
        name: 'file',
        header: { Authorization: `Bearer ${getToken()}` },
        success: (res) => {
          try {
            const data = JSON.parse(res.data)
            if (data.code === 200) {
              resolve(data.data)
            } else {
              reject(new Error(data.message || '上传失败'))
            }
          } catch {
            reject(new Error('上传响应解析失败'))
          }
        },
        fail: reject,
      })
    })
  },
}
