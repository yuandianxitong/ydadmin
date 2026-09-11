import type { PluginWidgetMeta } from '@/api/diy'

import { MEMBER_ONLY_TYPES, TYPE_LABELS, WIDGET_TYPES } from './useEditor'

export interface ComponentGroup {
    group: string
    items: { type: string; label: string; icon_url?: string }[]
}

/** 组件面板分组：按 pageKey 过滤内置 member 专属组件与插件 widget 的 pages 声明 */
export function buildComponentGroups(
    pageKey: string,
    pluginWidgets: PluginWidgetMeta[]
): ComponentGroup[] {
    const memberOnly = new Set<string>(MEMBER_ONLY_TYPES)
    const base = WIDGET_TYPES.filter((t) => !memberOnly.has(t))

    const groups: ComponentGroup[] = [
        { group: '基础组件', items: base.map((t) => ({ type: t, label: TYPE_LABELS[t] ?? t })) }
    ]

    if (pageKey === 'member') {
        groups.push({
            group: '个人中心组件',
            items: [...MEMBER_ONLY_TYPES].map((t) => ({ type: t, label: TYPE_LABELS[t] ?? t }))
        })
    }

    const plugins = pluginWidgets.filter((w) => !w.pages || w.pages.includes(pageKey))
    if (plugins.length) {
        groups.push({
            group: '插件组件',
            items: plugins.map((w) => ({
                type: w.type,
                label: w.label,
                icon_url: w.icon_url || undefined
            }))
        })
    }

    return groups
}
