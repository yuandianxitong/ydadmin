/** 浏览器识别顺序有讲究：Edge / Opera 的 UA 里同样带 Chrome 标记，必须排在 Chrome 之前。 */
const BROWSERS: Array<[RegExp, string]> = [
    [/Edg\/(\d+)/, 'Edge'],
    [/OPR\/(\d+)/, 'Opera'],
    [/Firefox\/(\d+)/, 'Firefox'],
    [/Chrome\/(\d+)/, 'Chrome'],
    [/Version\/(\d+)[\d.]*.*Safari\//, 'Safari']
]

/** 系统识别顺序：iPhone/iPad 的 UA 带「like Mac OS X」，Android 的 UA 带「Linux」，都要先判。 */
const SYSTEMS: Array<[RegExp, string]> = [
    [/iPhone|iPad/, 'iOS'],
    [/Android/, 'Android'],
    [/Windows NT/, 'Windows'],
    [/Mac OS X/, 'macOS'],
    [/Linux/, 'Linux']
]

function matchBrowser(ua: string): string {
    for (const [pattern, name] of BROWSERS) {
        const matched = ua.match(pattern)
        if (matched) return `${name} ${matched[1]}`
    }
    return ''
}

function matchSystem(ua: string): string {
    for (const [pattern, name] of SYSTEMS) {
        if (pattern.test(ua)) return name
    }
    return ''
}

/**
 * 把 User-Agent 缩写成「浏览器 主版本 / 系统」，供在线管理员列表展示；完整 UA 放在单元格 title 里。
 * 空 UA 返回 '-'；什么都识别不出来时原样截取前 40 个字符。
 */
export function summarizeUserAgent(ua: string): string {
    const text = ua.trim()
    if (text === '') return '-'
    const parts = [matchBrowser(text), matchSystem(text)].filter((part) => part !== '')
    return parts.length > 0 ? parts.join(' / ') : text.slice(0, 40)
}
