import { onShareAppMessage } from '@dcloudio/uni-app'

export function useShare(options?: { title?: string; path?: string; imageUrl?: string }) {
  onShareAppMessage(() => ({
    title: options?.title || '',
    path: options?.path || '/pages/index/index',
    imageUrl: options?.imageUrl || '',
  }))
}
