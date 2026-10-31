/** 与服务端 NameConvention 一致：仅移除配置的表前缀及末尾单个复数 s。 */
export function modelFromTable(table: string, prefix = ''): string {
    let stem = prefix && table.startsWith(prefix) ? table.slice(prefix.length) : table
    if (stem.endsWith('s') && !stem.endsWith('ss')) stem = stem.slice(0, -1)
    return stem
        .split('_')
        .filter(Boolean)
        .map((part) => part[0].toUpperCase() + part.slice(1).toLowerCase())
        .join('')
}
