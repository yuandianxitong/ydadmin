import { ref, toRaw } from 'vue'

import { diyApi, type MemberStatOption, type PluginWidgetMeta } from '@/api/diy'

// 模块级缓存：三个面板共享同一份插件目录
const pluginWidgets = ref<PluginWidgetMeta[]>([])
const metaByType = ref<Record<string, PluginWidgetMeta>>({})
const statOptions = ref<MemberStatOption[]>([])
let loaded = false

export function usePluginWidgets() {
    async function load() {
        if (loaded) return
        loaded = true
        try {
            const res = await diyApi.getWidgets()
            const plugins = res.data?.plugins || []
            pluginWidgets.value = plugins
            const map: Record<string, PluginWidgetMeta> = {}
            for (const p of plugins) map[p.type] = p
            metaByType.value = map
            statOptions.value = res.data?.member_stats || []
        } catch {
            // 无权限/失败 → 空目录，编辑器照常工作（仅无插件组件）
            loaded = false
        }
    }
    function isPlugin(type: string): boolean {
        return !!metaByType.value[type]
    }
    function metaOf(type: string): PluginWidgetMeta | null {
        // toRaw 解包 reactive Proxy：消费方（如 home.vue onAdd）会对
        // default_props 做 structuredClone，Proxy 会抛 DataCloneError
        const meta = metaByType.value[type]
        return meta ? toRaw(meta) : null
    }
    return { pluginWidgets, metaByType, statOptions, load, isPlugin, metaOf }
}
