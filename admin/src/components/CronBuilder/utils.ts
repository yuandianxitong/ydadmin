export type CronFrequency =
    | 'minute'
    | 'everyNMinute'
    | 'hour'
    | 'everyNHour'
    | 'day'
    | 'week'
    | 'month'

export interface ParsedCron {
    supported: boolean
    frequency: CronFrequency
    minute: number
    hour: number
    interval: number
    weekdays: number[]
    monthDays: number[]
}

const DEFAULT_PARSED: ParsedCron = {
    supported: false,
    frequency: 'minute',
    minute: 0,
    hour: 0,
    interval: 5,
    weekdays: [],
    monthDays: []
}

export function parseCron(expr: string | null | undefined): ParsedCron {
    const parsed: ParsedCron = { ...DEFAULT_PARSED }
    if (!expr || !expr.trim()) return parsed

    const parts = expr.trim().split(/\s+/)
    if (parts.length !== 5) return parsed

    const [mPart, hPart, dPart, monPart, wPart] = parts

    if (mPart === '*' && hPart === '*' && dPart === '*' && monPart === '*' && wPart === '*') {
        return { ...parsed, supported: true, frequency: 'minute' }
    }

    const everyNMin = mPart.match(/^\*\/(\d+)$/)
    if (everyNMin && hPart === '*' && dPart === '*' && monPart === '*' && wPart === '*') {
        return {
            ...parsed,
            supported: true,
            frequency: 'everyNMinute',
            interval: parseInt(everyNMin[1])
        }
    }

    const minuteOnly = mPart.match(/^(\d+)$/)
    if (minuteOnly && hPart === '*' && dPart === '*' && monPart === '*' && wPart === '*') {
        return {
            ...parsed,
            supported: true,
            frequency: 'hour',
            minute: parseInt(minuteOnly[1])
        }
    }

    const everyNHour = hPart.match(/^\*\/(\d+)$/)
    if (minuteOnly && everyNHour && dPart === '*' && monPart === '*' && wPart === '*') {
        return {
            ...parsed,
            supported: true,
            frequency: 'everyNHour',
            minute: parseInt(mPart),
            interval: parseInt(everyNHour[1])
        }
    }

    const hourMatch = hPart.match(/^(\d+)$/)
    if (minuteOnly && hourMatch && dPart === '*' && monPart === '*' && wPart !== '*') {
        if (/^[\d,]+$/.test(wPart)) {
            const days = wPart.split(',').map(Number)
            if (days.every((d) => d >= 0 && d <= 6)) {
                return {
                    ...parsed,
                    supported: true,
                    frequency: 'week',
                    minute: parseInt(mPart),
                    hour: parseInt(hPart),
                    weekdays: days
                }
            }
        }
    }

    if (minuteOnly && hourMatch && dPart !== '*' && monPart === '*' && wPart === '*') {
        if (/^[\d,]+$/.test(dPart)) {
            const days = dPart.split(',').map(Number)
            if (days.every((d) => d >= 1 && d <= 31)) {
                return {
                    ...parsed,
                    supported: true,
                    frequency: 'month',
                    minute: parseInt(mPart),
                    hour: parseInt(hPart),
                    monthDays: days
                }
            }
        }
    }

    if (minuteOnly && hourMatch && dPart === '*' && monPart === '*' && wPart === '*') {
        return {
            ...parsed,
            supported: true,
            frequency: 'day',
            minute: parseInt(mPart),
            hour: parseInt(hPart)
        }
    }

    return parsed
}

const WEEKDAY_LABELS = ['周日', '周一', '周二', '周三', '周四', '周五', '周六']
const WEEKDAY_LABELS_EN = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

export function describeCron(
    expr: string | null | undefined,
    locale: 'zh-CN' | 'en-US' = 'zh-CN'
): string {
    const parsed = parseCron(expr)
    if (!parsed.supported) return ''

    const pad = (n: number) => String(n).padStart(2, '0')
    const time = `${pad(parsed.hour)}:${pad(parsed.minute)}`
    const isZh = locale === 'zh-CN'
    const labels = isZh ? WEEKDAY_LABELS : WEEKDAY_LABELS_EN

    switch (parsed.frequency) {
        case 'minute':
            return isZh ? '每分钟执行一次' : 'Every minute'
        case 'everyNMinute':
            return isZh
                ? `每隔 ${parsed.interval} 分钟执行一次`
                : `Every ${parsed.interval} minutes`
        case 'hour':
            return isZh
                ? `每小时第 ${parsed.minute} 分执行`
                : `Every hour at minute ${parsed.minute}`
        case 'everyNHour':
            return isZh
                ? `每隔 ${parsed.interval} 小时，第 ${parsed.minute} 分执行`
                : `Every ${parsed.interval} hours at minute ${parsed.minute}`
        case 'day':
            return isZh ? `每天 ${time} 执行` : `Daily at ${time}`
        case 'week': {
            const days = [...parsed.weekdays].sort((a, b) => a - b).map((d) => labels[d])
            return isZh ? `每${days.join('、')} ${time} 执行` : `${days.join(', ')} at ${time}`
        }
        case 'month': {
            const days = [...parsed.monthDays].sort((a, b) => a - b)
            return isZh
                ? `每月 ${days.join('、')} 日 ${time} 执行`
                : `Day ${days.join(', ')} of each month at ${time}`
        }
    }
}
