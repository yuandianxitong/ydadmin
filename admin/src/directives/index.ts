// src/directives/index.ts
import type { App } from 'vue'

import copy from './copy'
import permsDirective from './perms'

export function installDirectives(app: App) {
    // 安装权限指令
    app.use(permsDirective)

    // 安装复制指令
    app.directive('copy', copy)
}
