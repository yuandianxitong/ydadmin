import { getToken } from '@/utils/auth'

const BASE_URL = import.meta.env.VITE_APP_API_URL || ''

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
