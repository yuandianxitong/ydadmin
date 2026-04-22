/**
 * 计算颜色透明度减淡
 */
export const calcColor = (color: string, opacity: number): string => {
    // 规范化透明度值在 0 ~ 1 之间
    opacity = Math.min(1, Math.max(0, opacity))

    // 检查颜色是否是 hex 格式
    const isHex = /^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/
    const isRgb = /^rgb\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*\)$/
    const isRgba = /^rgba\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*,\s*[0-9.]+\s*\)$/

    let r: number = 0,
        g: number = 0,
        b: number = 0

    if (isHex.test(color)) {
        const hex = color.slice(1)
        const fullHex =
            hex.length === 3
                ? hex
                      .split('')
                      .map((h) => h + h)
                      .join('')
                : hex

        r = parseInt(fullHex.substring(0, 2), 16)
        g = parseInt(fullHex.substring(2, 4), 16)
        b = parseInt(fullHex.substring(4, 6), 16)
    } else if (isRgb.test(color)) {
        const rgbValues = color.match(/\d+/g)
        if (rgbValues) {
            r = parseInt(rgbValues[0])
            g = parseInt(rgbValues[1])
            b = parseInt(rgbValues[2])
        }
    } else if (isRgba.test(color)) {
        const rgbaValues = color.match(/\d+(\.\d+)?/g)
        if (rgbaValues) {
            r = parseInt(rgbaValues[0])
            g = parseInt(rgbaValues[1])
            b = parseInt(rgbaValues[2])
        }
    } else {
        throw new Error('Unsupported color format')
    }

    return `rgba(${r}, ${g}, ${b}, ${opacity})`
}
