import { computed, ref } from 'vue'

import type { ActiveColumn, ColumnConfigItem, ColumnStorageState, ProColumn } from './types'

const STORAGE_PREFIX = 'col-cfg:'

function readStorage(key: string): ColumnStorageState | null {
    try {
        const raw = localStorage.getItem(STORAGE_PREFIX + key)
        if (!raw) return null
        const value = JSON.parse(raw)
        if (
            !Array.isArray(value?.order) ||
            !Array.isArray(value?.hidden) ||
            !value?.fixed ||
            typeof value.fixed !== 'object'
        )
            return null
        return value
    } catch {
        return null
    }
}

function writeStorage(key: string, state: ColumnStorageState) {
    try {
        localStorage.setItem(STORAGE_PREFIX + key, JSON.stringify(state))
    } catch {
        // Column settings remain usable when browser storage is unavailable.
    }
}

export function useColumnConfig(storageKey: string, columns: () => ProColumn[]) {
    const colCfgVisible = ref(false)
    const memoryState = ref<ColumnStorageState | null>(null)

    /** Merge ProColumn[] with localStorage state → ordered ActiveColumn[] */
    const activeColumns = computed<ActiveColumn[]>(() => {
        const cols = columns()
        const saved = memoryState.value ?? readStorage(storageKey)
        if (!saved) {
            return cols.map((c) => ({
                ...c,
                visible: c.required || c.defaultVisible !== false
            }))
        }

        // Build a map of all columns by key
        const colMap = new Map(cols.map((c) => [c.key, c]))

        // Start with saved order (skip keys that no longer exist)
        const ordered: ActiveColumn[] = []
        const seen = new Set<string>()

        for (const key of saved.order) {
            const col = colMap.get(key)
            if (!col) continue
            seen.add(key)
            ordered.push({
                ...col,
                visible: col.required ? true : !saved.hidden.includes(key),
                fixed: saved.fixed[key] || col.fixed || false
            })
        }

        // Append any new columns not in saved order
        for (const col of cols) {
            if (seen.has(col.key)) continue
            ordered.push({
                ...col,
                visible: col.required || col.defaultVisible !== false
            })
        }

        return ordered
    })

    /** Only visible columns for rendering */
    const visibleColumns = computed(() => activeColumns.value.filter((c) => c.visible))

    /** Convert to ColumnConfigItem[] for ColumnConfig dialog */
    const columnConfigItems = computed<ColumnConfigItem[]>(() =>
        activeColumns.value.map((c) => ({
            key: c.key,
            label: c.label,
            visible: c.visible,
            fixed: c.fixed || false,
            width: typeof c.width === 'number' ? c.width : undefined,
            required: c.required
        }))
    )

    /** Handle ColumnConfig dialog confirm */
    function onColumnConfigChange(items: ColumnConfigItem[]) {
        const state: ColumnStorageState = {
            order: items.map((i) => i.key),
            hidden: items.filter((i) => !i.visible).map((i) => i.key),
            fixed: {}
        }
        for (const item of items) {
            if (item.fixed) {
                state.fixed[item.key] = item.fixed
            }
        }
        memoryState.value = state
        writeStorage(storageKey, state)
    }

    return {
        colCfgVisible,
        activeColumns,
        visibleColumns,
        columnConfigItems,
        onColumnConfigChange
    }
}
