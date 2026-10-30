export interface ProColumn {
    /** Column key — also used as slot name */
    key: string
    /** Header label text */
    label: string
    /** el-table-column prop, defaults to key */
    prop?: string
    width?: number | string
    minWidth?: number
    fixed?: 'left' | 'right' | false
    /** If true, column cannot be hidden or reordered */
    required?: boolean
    /** Whether visible by default (default: true) */
    defaultVisible?: boolean
    showOverflowTooltip?: boolean
    align?: 'left' | 'center' | 'right'
    sortable?: boolean | 'custom'
    /** Keep the current page's table-column formatter and other presentation props. */
    extraProps?: Record<string, unknown>
}

export interface ColumnStorageState {
    /** Column keys in user-defined order */
    order: string[]
    /** Column keys that are hidden */
    hidden: string[]
    /** Column keys with fixed positions */
    fixed: Record<string, 'left' | 'right'>
}

export interface ActiveColumn extends ProColumn {
    visible: boolean
}

/** Shared with ColumnConfig component */
export interface ColumnConfigItem {
    key: string
    label: string
    visible: boolean
    fixed?: 'left' | 'right' | false
    width?: number
    required?: boolean
}
