interface NoticeItem {
  link?: string
  id?: number | string
}

/** 保留装修链接；未配置链接时进入公告列表，有公告 ID 时进入详情。 */
export function resolveNoticeLink(item?: NoticeItem): string {
  if (typeof item?.link === 'string' && item.link.trim()) return item.link.trim()
  if (item?.id && /^[1-9]\d*$/.test(String(item.id))) {
    return '/modules/announcement/pages/announcement-detail?id=' + item.id
  }
  return '/modules/announcement/pages/announcement-list'
}
