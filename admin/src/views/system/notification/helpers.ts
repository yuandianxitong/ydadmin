import type { NotificationAdminOption } from '@/types/system'

/** target_type：全员广播 */
export const TARGET_ALL = 1
/** target_type：指定管理员 */
export const TARGET_ADMINS = 2

export interface RecipientOption extends NotificationAdminOption {
    /** 已选但尚未查到名称的 id 用占位项显示为 #id */
    placeholder?: boolean
}

/**
 * 合并收件人下拉选项：先放本次查询结果（按返回顺序、按 id 去重），再补上已选却不在结果里的 id。
 * 已选 id 优先沿用之前查到的真实选项；从未查到过的用占位项 #id，等搜到后被真实选项替换。
 * 未选中且本次没查到的旧选项不保留，避免下拉越积越长。
 */
export function mergeRecipientOptions(
    current: RecipientOption[],
    fetched: NotificationAdminOption[],
    selectedIds: number[]
): RecipientOption[] {
    const merged = new Map<number, RecipientOption>()
    for (const option of fetched) {
        if (!merged.has(option.id)) {
            merged.set(option.id, {
                id: option.id,
                username: option.username,
                nickname: option.nickname
            })
        }
    }
    for (const id of selectedIds) {
        if (merged.has(id)) continue
        const known = current.find((option) => option.id === id && !option.placeholder)
        merged.set(id, known ?? { id, username: `#${id}`, nickname: '', placeholder: true })
    }
    return [...merged.values()]
}

/** 下拉选项文案：有昵称时「昵称（用户名）」，否则只显示用户名 */
export function recipientLabel(option: NotificationAdminOption): string {
    return option.nickname ? `${option.nickname}（${option.username}）` : option.username
}

/** 两组 id 是否代表同一集合（忽略顺序与重复） */
function sameIdSet(a: number[], b: number[]): boolean {
    if (a.length !== b.length) return false
    const setA = new Set(a)
    const setB = new Set(b)
    if (setA.size !== setB.size) return false
    for (const id of setA) {
        if (!setB.has(id)) return false
    }
    return true
}

/**
 * 提交前整理：广播不带 admin_ids；指定管理员带一份 admin_ids 的拷贝（后端对空列表返回 422）。
 *
 * 控制器裁决：编辑时详情把当前全部收件人（含超出编辑者数据范围、已被禁用或删除的管理员）都
 * 回填进表单；原样提交会被后端按越权 / 不存在 id 判 422。因此更新场景下只有当编辑者真的改动了
 * 收件人集合（与打开表单时加载的 originalAdminIds 比较，忽略顺序）才带上 admin_ids；未改动则
 * 省略该字段。create 场景（或调用方未提供 originalAdminIds，即没有可比较的基准）总是带上。
 */
export function buildNotificationPayload<T extends { target_type: number; admin_ids?: number[] }>(
    form: T,
    originalAdminIds?: number[]
): Omit<T, 'admin_ids'> & { admin_ids?: number[] } {
    const { admin_ids: adminIds, ...rest } = form
    if (form.target_type !== TARGET_ADMINS) {
        return rest
    }
    const ids = [...(adminIds ?? [])]
    if (originalAdminIds && sameIdSet(ids, originalAdminIds)) {
        return rest
    }
    return { ...rest, admin_ids: ids }
}
