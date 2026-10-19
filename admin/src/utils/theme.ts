import colors from 'css-color-function'

/** Toggle html.dark class */
export const applyDarkClass = (isDark: boolean) => {
    document.documentElement.classList.toggle('dark', isDark)
}

/** Set data-dark-theme attribute on html */
export const applyDarkTheme = (theme: string) => {
    if (theme) {
        document.documentElement.setAttribute('data-dark-theme', theme)
    } else {
        document.documentElement.removeAttribute('data-dark-theme')
    }
}

const lightConfig = {
    'dark-2': 'shade(20%)',
    'light-3': 'tint(30%)',
    'light-5': 'tint(50%)',
    'light-7': 'tint(70%)',
    'light-8': 'tint(80%)',
    'light-9': 'tint(90%)'
}

const darkConfig = {
    'light-3': 'shade(20%)',
    'light-5': 'shade(30%)',
    'light-7': 'shade(50%)',
    'light-8': 'shade(60%)',
    'light-9': 'shade(70%)',
    'dark-2': 'tint(20%)'
}

/**
 * @author Jason
 * @description 用于生成elementui主题的行为变量
 * 可选值有primary、success、warning、danger、error、info
 */

export const generateVars = (color: string, type = 'primary', isDark = false) => {
    const colos = {
        [`--el-color-${type}`]: color
    }
    const config: Record<string, string> = isDark ? darkConfig : lightConfig
    for (const key in config) {
        colos[`--el-color-${type}-${key}`] = `color(${color} ${config[key]})`
    }
    return colos
}

/**
 * @author Jason
 * @description 用于设置css变量
 * @param key css变量key 如 --color-primary
 * @param value css变量值 如 #f40
 * @param dom dom元素
 */
export const setCssVar = (key: string, value: string, dom = document.documentElement) => {
    dom.style.setProperty(key, value)
}

/**
 * @author Jason
 * @description 设置主题
 */
export const setTheme = (options: Record<string, string>, isDark = false) => {
    const varsMap: Record<string, string> = Object.keys(options).reduce((prev, key) => {
        return Object.assign(prev, generateVars(options[key], key, isDark))
    }, {})

    // 行内变量优先于 html.dark 及懒加载组件样式，刷新后也使用已保存的主色。
    for (const [key, value] of Object.entries(varsMap)) {
        setCssVar(key, colors.convert(value))
    }

    // 同步更新设计 token 层的品牌色，让侧边栏等使用 --color-brand 的组件跟随变化
    if (options.primary) {
        setCssVar('--color-brand', options.primary)
        setCssVar('--color-brand-ghost', colors.convert(`color(${options.primary} a(15%))`))
    }
}
