export interface PluginAssetEntry {
    code: string
    base: string
    pages: Record<string, string>
    /** 相对 base 的样式文件；旧接口没有这个字段时按空数组处理 */
    styles?: string[]
}

export interface ResolvedPage {
    pluginCode: string
    url: string
    /** 这个插件的样式表地址，加载页面前要先挂上 */
    styles: string[]
}

const PAGE_PATH = /^[A-Za-z0-9._~/-]+$/

let entries: Required<PluginAssetEntry>[] = []

function isValidBase(base: unknown): base is string {
    return typeof base === 'string' && base.startsWith('/') && !base.startsWith('//') && base.endsWith('/')
}

function isValidPagePath(path: unknown): path is string {
    return typeof path === 'string' && path !== '' && PAGE_PATH.test(path) && !path.includes('..')
}

function sanitize(entry: PluginAssetEntry): Required<PluginAssetEntry> | null {
    if (!entry || typeof entry.code !== 'string' || entry.code === '') return null
    if (!isValidBase(entry.base)) return null
    if (!entry.pages || typeof entry.pages !== 'object') return null

    const pages: Record<string, string> = {}
    for (const [key, file] of Object.entries(entry.pages)) {
        if (!isValidPagePath(file)) return null
        pages[key] = file
    }

    const styles: string[] = []
    for (const file of Array.isArray(entry.styles) ? entry.styles : []) {
        if (!isValidPagePath(file)) return null
        styles.push(file)
    }
    return { code: entry.code, base: entry.base, pages, styles }
}

/** 整体替换已知的插件产物清单；不合法的条目被丢掉。 */
export function setPluginAssets(list: PluginAssetEntry[]): void {
    entries = (Array.isArray(list) ? list : [])
        .map((entry) => sanitize(entry))
        .filter((entry): entry is Required<PluginAssetEntry> => entry !== null)
}

/** 按菜单 component 找到插件页的 js 地址和样式表地址；找不到返回 null。 */
export function resolvePage(component: string): ResolvedPage | null {
    const relative = component.replace(/^\//, '')
    for (const entry of entries) {
        if (Object.prototype.hasOwnProperty.call(entry.pages, relative)) {
            return {
                pluginCode: entry.code,
                url: entry.base + entry.pages[relative],
                styles: entry.styles.map((file) => entry.base + file),
            }
        }
    }
    return null
}
