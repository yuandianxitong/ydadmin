/**
 * 时间/日期格式化工具
 *
 * 统一项目中多处独立实现的日期处理逻辑。
 */

/**
 * 将日期字符串截取为 YYYY-MM-DD 格式
 *
 * 输入 `"2026-04-06 12:34:56"` → `"2026-04-06"`
 */
export function formatDate(dateStr: string): string {
  if (!dateStr) return ''
  return dateStr.substring(0, 10)
}

/**
 * 将 Date 或时间字符串格式化为 YYYY-MM-DD
 */
export function formatDateFull(date: Date | string): string {
  const d = typeof date === 'string' ? new Date(date) : date
  if (isNaN(d.getTime())) return ''
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

/**
 * 将时间字符串格式化为 YYYY-MM-DD HH:mm:ss
 */
export function formatDateTime(date: Date | string): string {
  const d = typeof date === 'string' ? new Date(date) : date
  if (isNaN(d.getTime())) return ''
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  const hours = String(d.getHours()).padStart(2, '0')
  const minutes = String(d.getMinutes()).padStart(2, '0')
  const seconds = String(d.getSeconds()).padStart(2, '0')
  return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`
}

/**
 * 将时间字符串格式化为相对时间
 *
 * - < 1 分钟：刚刚
 * - < 60 分钟：X 分钟前
 * - < 24 小时：X 小时前
 * - < 7 天：X 天前
 * - 否则：MM-DD
 */
export function formatRelativeTime(time: string): string {
  if (!time) return ''
  const date = new Date(time)
  if (isNaN(date.getTime())) return ''

  const now = new Date()
  const diff = now.getTime() - date.getTime()
  const minutes = Math.floor(diff / 60000)
  if (minutes < 1) return '刚刚'
  if (minutes < 60) return `${minutes}分钟前`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}小时前`
  const days = Math.floor(hours / 24)
  if (days < 7) return `${days}天前`
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${month}-${day}`
}
