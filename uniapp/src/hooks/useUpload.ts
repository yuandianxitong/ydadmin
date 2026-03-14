import { uploadApi } from '@/api/upload'

interface UploadOptions {
  maxSize?: number  // MB
}

export function useUpload(options: UploadOptions = {}) {
  const { maxSize = 10 } = options

  function chooseAndUpload(): Promise<string> {
    return new Promise((resolve, reject) => {
      uni.chooseImage({
        count: 1,
        sizeType: ['compressed'],
        success: async (res) => {
          const files = res.tempFiles as Array<{ path: string; size: number }>
          const file = files[0]
          if (file.size > maxSize * 1024 * 1024) {
            uni.showToast({ title: `文件不能超过${maxSize}MB`, icon: 'none' })
            return reject(new Error('文件过大'))
          }

          try {
            const result = await uploadApi.uploadImage(file.path)
            resolve(result.url)
          } catch (e) {
            reject(e)
          }
        },
        fail: reject,
      })
    })
  }

  return { chooseAndUpload }
}
