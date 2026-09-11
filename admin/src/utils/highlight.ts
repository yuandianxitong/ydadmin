import hljs from 'highlight.js'
import type { App } from 'vue'

export function installHighlight(app: App) {
    app.directive('highlight', {
        mounted(el) {
            const blocks = el.querySelectorAll('pre code')
            blocks.forEach((block: any) => hljs.highlightElement(block))
        }
    })
}
