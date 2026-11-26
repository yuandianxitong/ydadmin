import { resolve } from 'node:path'
import type { Plugin } from 'vite'

const root = resolve(__dirname, '..')

/**
 * 宿主向插件页暴露的模块。key 是 import map 里的裸说辞，value 是宿主内的入口文件。
 * 插件产物把这些说辞当 external，浏览器在运行时经 import map 解析到宿主同一份模块实例。
 */
export const HOST_MODULES: Record<string, string> = {
    'vue': 'src/host-sdk/vendor/vue.ts',
    'vue-router': 'src/host-sdk/vendor/vue-router.ts',
    'pinia': 'src/host-sdk/vendor/pinia.ts',
    'vue-i18n': 'src/host-sdk/vendor/vue-i18n.ts',
    'element-plus': 'src/host-sdk/vendor/element-plus.ts',
    '@element-plus/icons-vue': 'src/host-sdk/vendor/element-plus-icons.ts',
    'axios': 'src/host-sdk/vendor/axios.ts',
    '@vueuse/core': 'src/host-sdk/vendor/vueuse-core.ts',
    '@yd/admin-host@1/http': 'src/host-sdk/http.ts',
    '@yd/admin-host@1/i18n': 'src/host-sdk/i18n.ts',
    '@yd/admin-host@1/router': 'src/host-sdk/router.ts',
    '@yd/admin-host@1/store': 'src/host-sdk/store.ts',
}

/** rollup 入口名（也是产物文件名前缀）。不含 `/` 和 `@`，保证产物落在 js/ 下一层。 */
const entryName = (specifier: string): string => 'host-' + specifier.replace(/^@/, '').replace(/[^A-Za-z0-9]+/g, '-')

const ENTRY_NAME_TO_SPECIFIER = new Map(Object.keys(HOST_MODULES).map((s) => [entryName(s), s]))

export function injectAdminImportMap(): Plugin {
    let base = '/'
    let command: 'build' | 'serve' = 'build'

    return {
        name: 'injectAdminImportMap',
        enforce: 'post',
        config(_config, env) {
            command = env.command
            if (env.command !== 'build') return
            const input: Record<string, string> = { index: resolve(root, 'index.html') }
            for (const specifier of Object.keys(HOST_MODULES)) {
                input[entryName(specifier)] = resolve(root, HOST_MODULES[specifier])
            }
            return {
                build: {
                    rollupOptions: {
                        input,
                        // 入口的导出必须原样保留，否则 tree-shaking 会把 `export *` 裁空。
                        preserveEntrySignatures: 'strict',
                    },
                },
            }
        },
        configResolved(resolved) {
            base = resolved.base
        },
        transformIndexHtml() {
            if (command !== 'serve') return
            const imports = Object.fromEntries(
                Object.entries(HOST_MODULES).map(([specifier, source]) => [specifier, base + source])
            )
            return [{ tag: 'script', attrs: { type: 'importmap' }, children: JSON.stringify({ imports }), injectTo: 'head-prepend' }]
        },
        generateBundle(_options, bundle) {
            if (command !== 'build') return

            const imports: Record<string, string> = {}
            for (const chunk of Object.values(bundle)) {
                if (chunk.type !== 'chunk' || !chunk.isEntry) continue
                const specifier = ENTRY_NAME_TO_SPECIFIER.get(chunk.name)
                if (specifier) imports[specifier] = base + chunk.fileName
            }

            const html = bundle['index.html']
            if (!html || html.type !== 'asset') return

            const tag = `<script type="importmap">${JSON.stringify({ imports })}</script>`
            const source = typeof html.source === 'string' ? html.source : Buffer.from(html.source).toString('utf8')
            // import map 必须先于任何模块脚本，所以插在 <head> 的最前面。
            html.source = source.replace(/<head([^>]*)>/i, (m) => `${m}\n    ${tag}`)
        },
    }
}
