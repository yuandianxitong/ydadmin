import ElementPlus from 'element-plus'
import type { App } from 'vue'

export function installElementPlus(app: App) {
    app.use(ElementPlus)
}
