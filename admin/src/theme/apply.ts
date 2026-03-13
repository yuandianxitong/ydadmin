// src/theme/apply.ts
export type ThemeName = 'blue' | 'cyan' | 'green' | 'orange' | 'purple'

export function applyTheme(name: ThemeName) {
    const root = document.documentElement
    if (name === 'blue') {
        root.removeAttribute('data-theme') // 默认主题用 :root
    } else {
        root.setAttribute('data-theme', name)
    }
    localStorage.setItem('APP_THEME', name)
}

/* 可选：单次覆盖某个 Token（比如动态场景色） */
export function setToken(name: string, value: string) {
    document.documentElement.style.setProperty(name, value)
}
