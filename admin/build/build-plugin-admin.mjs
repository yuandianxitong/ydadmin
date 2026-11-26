#!/usr/bin/env node
/**
 * 把 server/plugins/<code>/src/admin 编成 server/plugins/<code>/dist/admin。
 *
 * 用法（在 admin/ 下）：node build/build-plugin-admin.mjs <code>
 *
 * 每个 src/admin/**\/index.vue 是一个入口，页面键是 plugins/<code>/<相对 src/admin 的路径，去掉 .vue>。
 * 宿主已有的运行时依赖一律 external，由后台 index.html 的 import map 在浏览器里解析到宿主同一份模块。
 */
import vue from '@vitejs/plugin-vue'
import { existsSync, readdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, join, relative, resolve, sep } from 'node:path'
import { fileURLToPath } from 'node:url'
import AutoImport from 'unplugin-auto-import/vite'
import { ElementPlusResolver } from 'unplugin-vue-components/resolvers'
import Components from 'unplugin-vue-components/vite'
import { build } from 'vite'

const adminRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const pathSrc = resolve(adminRoot, 'src')
const pluginsRoot = resolve(adminRoot, '../server/plugins')
/**
 * 插件构建里宿主模块的替身，各自转发到外部的 `@yd/admin-host@1/*`。
 * 请求实例、i18n 实例、路由实例和 pinia 都只有宿主那一份，插件产物不带副本。
 */
const HOST_SHIMS = [
    { find: /^@\/utils\/request(\.ts)?$/, replacement: resolve(adminRoot, 'build/shims/request.ts') },
    { find: /^@\/utils\/i18n(\.ts)?$/, replacement: resolve(adminRoot, 'build/shims/i18n.ts') },
    { find: /^@\/router(\/index(\.ts)?)?$/, replacement: resolve(adminRoot, 'build/shims/router.ts') },
    { find: /^@\/store(\/index(\.ts)?)?$/, replacement: resolve(adminRoot, 'build/shims/store.ts') },
    // 宿主组件里也有绕过 `@/store` 直接引单个仓库文件的写法，默认导出是仓库本身，所以各有一个替身
    {
        find: /^@\/store\/modules\/(app|multipleTabs|settings|user)\.store(\.ts)?$/,
        replacement: resolve(adminRoot, 'build/shims/store-modules') + '/$1.ts',
    },
]

/** 与 HostProfile 的 admin vendors 加四个 `@yd/admin-host@1/*` 模块一致，写进清单的 externals 也是这一份。 */
export const EXTERNALS = [
    'vue',
    'vue-router',
    'pinia',
    'vue-i18n',
    '@vueuse/core',
    'element-plus',
    '@element-plus/icons-vue',
    'axios',
    '@yd/admin-host@1/http',
    '@yd/admin-host@1/i18n',
    '@yd/admin-host@1/router',
    '@yd/admin-host@1/store',
]

/** 产物要求的 Host SDK 区间，宿主版本在 server/config/version.php 的 host_sdk。 */
export const HOST_SDK_RANGE = '^1.0.0'

/** ElementPlusResolver 生成的组件导入来源是 element-plus/es，它与宿主导出的 element-plus 是同一份。 */
const EXTERNAL_ALIASES = { 'element-plus/es': 'element-plus' }

function findIndexVues(dir, base = dir) {
    const found = []
    for (const entry of readdirSync(dir, { withFileTypes: true })) {
        const full = join(dir, entry.name)
        if (entry.isDirectory()) {
            found.push(...findIndexVues(full, base))
        } else if (entry.isFile() && entry.name === 'index.vue') {
            found.push(relative(base, full).split(sep).join('/'))
        }
    }
    return found.sort()
}

export async function buildPluginAdmin(code) {
    if (!/^[A-Za-z0-9_-]+$/.test(code ?? '')) {
        throw new Error(`非法的插件 code：${code}`)
    }
    const pluginDir = resolve(pluginsRoot, code)
    const srcAdmin = join(pluginDir, 'src/admin')
    const pluginJson = join(pluginDir, 'plugin.json')
    if (!existsSync(pluginJson)) throw new Error(`找不到 ${pluginJson}`)
    if (!existsSync(srcAdmin)) throw new Error(`找不到 ${srcAdmin}`)

    const meta = JSON.parse(readFileSync(pluginJson, 'utf8'))
    const files = findIndexVues(srcAdmin)
    if (files.length === 0) throw new Error(`${srcAdmin} 下没有 index.vue`)

    // 入口名 = 页面键里 plugins/<code>/ 之后的部分，也决定产物 js/<name>.js
    const input = {}
    for (const file of files) {
        input[file.replace(/\.vue$/, '')] = join(srcAdmin, file)
    }

    const outDir = join(pluginDir, 'dist/admin')
    const result = await build({
        configFile: false,
        root: adminRoot,
        base: './',
        publicDir: false,
        logLevel: 'warn',
        resolve: {
            // 插件源码在 admin/ 之外，裸依赖必须钉死到 admin/node_modules
            dedupe: ['vue', 'vue-router', 'pinia', 'vue-i18n', 'element-plus', '@element-plus/icons-vue'],
            alias: [
                // 必须排在通用的 `@` 之前，否则这几个模块会连同拦截器、locales、路由表和登录页一起打进插件
                ...HOST_SHIMS,
                { find: '@', replacement: pathSrc },
                {
                    find: /^element-plus\/es\/(.+)$/,
                    replacement: resolve(adminRoot, 'node_modules/element-plus/es/$1'),
                },
            ],
        },
        plugins: [
            vue(),
            // importStyle: false：Element Plus 的 :root 变量和组件样式都由宿主提供，
            // 宿主在加载插件页之前用 src/plugin-runtime/element-styles.ts 把组件样式补齐。
            // 插件再带一份的话，它的样式表挂在宿主之后，会把宿主主题对 --el-* 变量的覆盖盖回默认值。
            AutoImport({
                imports: ['vue', '@vueuse/core', 'pinia', 'vue-router'],
                resolvers: [ElementPlusResolver({ importStyle: false })],
                dts: false,
            }),
            Components({
                resolvers: [ElementPlusResolver({ importStyle: false })],
                dirs: [],
                dts: false,
            }),
        ],
        build: {
            outDir,
            emptyOutDir: true,
            cssCodeSplit: false,
            rollupOptions: {
                input,
                preserveEntrySignatures: 'strict',
                external: (id) => EXTERNALS.includes(id) || id in EXTERNAL_ALIASES,
                output: {
                    format: 'es',
                    entryFileNames: 'js/[name].js',
                    chunkFileNames: 'js/chunks/[name].[hash].js',
                    assetFileNames: (info) =>
                        /\.css$/i.test(info.names?.[0] ?? info.name ?? '')
                            ? 'css/[name].[hash][extname]'
                            : 'assets/[name].[hash][extname]',
                    paths: EXTERNAL_ALIASES,
                },
            },
        },
    })

    const outputs = (Array.isArray(result) ? result : [result]).flatMap((r) => r.output ?? [])
    const pages = {}
    for (const item of outputs) {
        if (item.type === 'chunk' && item.isEntry && item.name in input) {
            pages[`plugins/${code}/${item.name}`] = item.fileName
        }
    }
    const styles = outputs
        .filter((item) => item.type === 'asset' && item.fileName.endsWith('.css'))
        .map((item) => item.fileName)
        .sort()

    const manifest = {
        schema: 1,
        host: 'admin',
        code: meta.code ?? code,
        version: meta.version ?? '',
        hostSdk: HOST_SDK_RANGE,
        externals: [...EXTERNALS],
        styles,
        pages,
    }
    writeFileSync(join(outDir, 'manifest.json'), JSON.stringify(manifest, null, 2) + '\n')

    return manifest
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    buildPluginAdmin(process.argv[2]).then(
        (manifest) => {
            console.log(`built ${manifest.code}@${manifest.version}: ${Object.keys(manifest.pages).length} page(s)`)
        },
        (error) => {
            console.error(error instanceof Error ? error.message : error)
            process.exit(1)
        }
    )
}
