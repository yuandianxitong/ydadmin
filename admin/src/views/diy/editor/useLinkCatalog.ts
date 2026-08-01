import { computed, ref } from 'vue'

import { type CatalogLink, diyApi } from '@/api/diy'

const links = ref<CatalogLink[]>([])
let loaded = false

/** 用 params 替换 path 中的 {key} 占位（缺失的 key 保持原样）。 */
export function applyLinkParams(path: string, params: Record<string, string>): string {
    return path.replace(/\{(\w+)\}/g, (m, k) =>
        params[k] != null && params[k] !== '' ? params[k] : m
    )
}

export function useLinkCatalog() {
    async function load(force = false) {
        if (loaded && !force) return
        try {
            const res = await diyApi.getLinkCatalog()
            links.value = res.data?.links || []
            loaded = true
        } catch {
            loaded = false
        }
    }
    // 按 category 分组（保持首次出现顺序）
    const groups = computed(() => {
        const map = new Map<string, CatalogLink[]>()
        for (const l of links.value) {
            if (!map.has(l.category)) map.set(l.category, [])
            map.get(l.category)!.push(l)
        }
        return Array.from(map, ([category, items]) => ({ category, items }))
    })
    return { links, groups, load }
}
