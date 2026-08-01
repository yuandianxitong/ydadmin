import vue from "@vitejs/plugin-vue";
import { type ConfigEnv, loadEnv, defineConfig } from "vite";

import AutoImport from "unplugin-auto-import/vite";
import Components from "unplugin-vue-components/vite";
import { ElementPlusResolver } from "unplugin-vue-components/resolvers";

import UnoCSS from "unocss/vite";
import { resolve } from "path";
import { readdirSync, existsSync } from "fs";
import { name, version, engines, dependencies, devDependencies } from "./package.json";

// 动态解析 Element Plus 所有组件样式路径，避免开发模式下依赖重优化导致页面刷新
function getElementPlusStyleIncludes(): string[] {
    const dir = resolve(__dirname, "node_modules/element-plus/es/components");
    try {
        return readdirSync(dir)
            .filter((name) => existsSync(resolve(dir, name, "style")))
            .map((name) => `element-plus/es/components/${name}/style/index`);
    } catch {
        return [];
    }
}

// 平台的名称、版本、运行所需的 node 版本、依赖、构建时间的类型提示
const __APP_INFO__ = {
    pkg: { name, version, engines, dependencies, devDependencies },
    buildTimestamp: Date.now(),
};

// 定义项目源码根路径
const pathSrc = resolve(__dirname, "src");

// 以下别名可根据重构后的目录结构进行调整
// @          -> src

export default defineConfig(({ mode }: ConfigEnv) => {
    // 加载环境变量（.env.development、.env.production 等）
    const env = loadEnv(mode, process.cwd());

    return {
        // 生产环境部署到 /admin/ 子目录
        base: "/admin/",
        resolve: {
            alias: {
                "@": pathSrc
            },
        },

        css: {
            preprocessorOptions: {
                scss: {

                },
            },
        },

        server: {
            host: "0.0.0.0",
            port: +env.VITE_APP_PORT || 5174,
            open: true,
            proxy: {
                // 代理 /adminapi 的请求到后端API
                '/adminapi': {
                    target: env.VITE_APP_API_URL || 'http://test.com',
                    changeOrigin: true,
                    secure: false,
                    rewrite: (path) => path.replace(/^\/adminapi/, '/adminapi'),
                    configure: (proxy) => {
                        proxy.on('error', (err) => {
                            console.error('proxy error', err);
                        });
                    }
                },
                // 代理 /storage 静态资源到后端（图片、上传文件等）
                '/storage': {
                    target: env.VITE_APP_API_URL || 'http://test.com',
                    changeOrigin: true,
                    secure: false,
                },
                // 装修默认素材等 public/static 资源
                '/static': {
                    target: env.VITE_APP_API_URL || 'http://test.com',
                    changeOrigin: true,
                    secure: false,
                },
            },
        },

        plugins: [
            vue(),
            UnoCSS(),

            // ========== API 自动导入 ==========
            AutoImport({
                // 导入 Vue 函数，如：ref、reactive、toRef 等
                imports: ["vue", "@vueuse/core", "pinia", "vue-router", "vue-i18n"],
                resolvers: [
                    // 导入 Element Plus 函数，如：ElMessage、ElMessageBox 等
                    ElementPlusResolver({ importStyle: "sass" }),
                ],
                eslintrc: {
                    enabled: true,
                    filepath: "./.eslintrc-auto-import.json",
                    globalsPropValue: true,
                },
                vueTemplate: true,
                // dts: "src/types/auto-imports.d.ts", // 如需生成类型文件可打开
                dts: false,
            }),

            // ========== 组件自动导入 ==========
            Components({
                resolvers: [
                    // 导入 Element Plus 组件
                    ElementPlusResolver({ importStyle: "sass" }),
                ],
                // 通用组件目录
                dirs: [
                    "src/components",
                ],
                // dts: "src/types/components.d.ts", // 如需生成类型文件可打开
                dts: false,
            }),
        ],

        // 预加载项目必需的依赖包
        optimizeDeps: {
            include: [
                "vue",
                "vue-router",
                "element-plus",
                ...getElementPlusStyleIncludes(),
                "pinia",
                "axios",
                "@vueuse/core",
                "default-passive-events",
                "nprogress",
                "qs",
                "@stomp/stompjs",
                "@element-plus/icons-vue",
                "vue-i18n",
                "echarts/core",
                "echarts/charts",
                "echarts/components",
                "echarts/renderers",
            ],
        },

        build: {
            // 构建产物输出到 server/public/admin/，部署时无需手动复制
            outDir: resolve(__dirname, '../server/public/admin'),
            emptyOutDir: true,
            chunkSizeWarningLimit: 2000,
            minify: "terser",
            terserOptions: {
                compress: {
                    keep_infinity: true,
                    drop_console: true,
                    drop_debugger: true,
                },
                format: {
                    comments: false,
                },
            },
            rollupOptions: {
                output: {
                    // 入口文件打包输出
                    entryFileNames: "js/[name].[hash].js",
                    // 代码拆分后共享块输出
                    chunkFileNames: "js/[name].[hash].js",
                    // 静态资源命名：按类型分类
                    assetFileNames: (assetInfo: any) => {
                        const info = assetInfo.name.split(".");
                        let extType = info[info.length - 1];
                        if (
                            /\.(mp4|webm|ogg|mp3|wav|flac|aac)(\?.*)?$/i.test(
                                assetInfo.name
                            )
                        ) {
                            extType = "media";
                        } else if (
                            /\.(png|jpe?g|gif|svg)(\?.*)?$/.test(assetInfo.name)
                        ) {
                            extType = "img";
                        } else if (
                            /\.(woff2?|eot|ttf|otf)(\?.*)?$/i.test(
                                assetInfo.name
                            )
                        ) {
                            extType = "fonts";
                        }
                        return `${extType}/[name].[hash].[ext]`;
                    },
                },
            },
        },

        define: {
            __APP_INFO__: JSON.stringify(__APP_INFO__),
        },

        test: {
            environment: 'happy-dom',
            globals: true,
            include: ['src/**/*.{test,spec}.{js,ts}'],
        },
    };
});
