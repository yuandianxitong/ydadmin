import { messageApi, type NotificationInfo } from '@/api/message'
import { usePaging } from '@/hooks/usePaging'
import { formatRelativeTime } from '@/utils/time'

/**
 * 跳转详情时传递消息对象的临时缓存（按 id 索引）
 *
 * 消息内容可能超出微信小程序路由参数 1024 字节限制，因此不再把整个对象序列化进 URL，
 * 改为：列表页点击时把对象写入此 Map，详情页通过 id 读取并消费；
 * 读取后立即删除，避免无限堆积。
 *
 * 注意：此文件位于 hooks/ 目录而非 modules/message/composables/，
 * 因为 uniapp 分包架构下，主包的 pages/message/index.vue 不能 import 子包文件。
 * hooks/ 是项目级共享目录，主包和所有子包都能访问。
 */
const messageCache = new Map<number, NotificationInfo>()

export function consumeMessageCache(id: number): NotificationInfo | null {
  const item = messageCache.get(id) ?? null
  if (item) messageCache.delete(id)
  return item
}

/**
 * 消息列表页共享逻辑
 *
 * 主包的 `pages/message/index.vue` 和子包的 `modules/message/pages/message-list.vue`
 * 两个页面视觉设计不同，但数据获取、时间格式化、已读标记、跳转详情等业务逻辑一致。
 * 抽取为 composable 避免两边手动维护相同代码。
 */
export function useMessageList() {
  const { list, loading, finished, refreshing, total, getList, refresh } = usePaging<NotificationInfo>({
    fetchFun: (params) => messageApi.getList(params),
  })

  /** 将时间戳格式化为相对时间（复用全局工具） */
  const formatTime = formatRelativeTime

  /**
   * 点击消息：静默标记单条已读并跳转详情
   *
   * 为避免小程序路由参数超过 1024 字节，
   * 将消息对象写入模块内缓存，URL 只传 id，详情页通过 consumeMessageCache 取回。
   */
  function handleTap(item: NotificationInfo) {
    if (!item.is_read) {
      messageApi.markAsRead([item.id]).then(() => {
        item.is_read = true
      })
    }
    messageCache.set(item.id, { ...item })
    uni.navigateTo({
      url: `/modules/message/pages/message-detail?id=${item.id}`,
    })
  }

  /** 全部已读 */
  async function handleReadAll() {
    if (total.value === 0) return
    try {
      await messageApi.markAsRead()
      uni.showToast({ title: '全部已读', icon: 'success' })
      list.value.forEach((item) => {
        item.is_read = true
      })
    } catch {
      // error handled by request interceptor
    }
  }

  return {
    list,
    loading,
    finished,
    refreshing,
    total,
    getList,
    refresh,
    formatTime,
    handleTap,
    handleReadAll,
  }
}
