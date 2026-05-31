const WEEKDAY_LABELS = ['周日', '周一', '周二', '周三', '周四', '周五', '周六']

const pad = (n: string | number) => String(n).padStart(2, '0')

export function cronToText(expr: string): string | null {
    if (!expr) return null
    const parts = expr.trim().split(/\s+/)
    if (parts.length !== 5) return null

    const [m, h, d, mon, w] = parts

    if (m === '*' && h === '*' && d === '*' && mon === '*' && w === '*') {
        return '每分钟执行一次'
    }

    const everyNMin = m.match(/^\*\/(\d+)$/)
    if (everyNMin && h === '*' && d === '*' && mon === '*' && w === '*') {
        return `每 ${everyNMin[1]} 分钟执行一次`
    }

    const minOnly = m.match(/^\d+$/)
    if (minOnly && h === '*' && d === '*' && mon === '*' && w === '*') {
        return `每小时第 ${m} 分执行`
    }

    const everyNHour = h.match(/^\*\/(\d+)$/)
    if (minOnly && everyNHour && d === '*' && mon === '*' && w === '*') {
        return `每 ${everyNHour[1]} 小时，第 ${m} 分执行`
    }

    const hourOnly = h.match(/^\d+$/)
    if (minOnly && hourOnly) {
        const timeStr = `${pad(h)}:${pad(m)}`

        if (d === '*' && mon === '*' && w === '*') {
            return `每天 ${timeStr} 执行`
        }
        if (d === '*' && mon === '*' && /^[\d,]+$/.test(w)) {
            const days = w
                .split(',')
                .map(Number)
                .sort((a, b) => a - b)
                .map((i) => WEEKDAY_LABELS[i] ?? '')
                .filter(Boolean)
                .join('、')
            return `每${days} ${timeStr} 执行`
        }
        if (/^[\d,]+$/.test(d) && mon === '*' && w === '*') {
            const days = d
                .split(',')
                .map(Number)
                .sort((a, b) => a - b)
                .join('、')
            return `每月 ${days} 日 ${timeStr} 执行`
        }
    }

    return null
}
