import { defineConfig } from "vite";
import uniModule from "@dcloudio/vite-plugin-uni";
import UnoCSS from "unocss/vite";

// CJS interop: @dcloudio/vite-plugin-uni uses exports.default in CJS
const uni = (uniModule as any).default || uniModule;

// uni-h5 的预加载 CSS 在常规 PostCSS 阶段之后合并，构建末尾也需替换。
function localShadows(css: string): string {
  return css.replace(/url\(["']?https:\/\/cdn\.dcloud\.net\.cn\/img\/shadow-[a-z]+\.png["']?\)/g,
    'linear-gradient(to bottom, rgba(0,0,0,.12), transparent)')
}

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [UnoCSS(), uni(), {
    name: 'local-h5-navigation-shadows',
    enforce: 'post',
    generateBundle: {
      order: 'post',
      handler(_options, bundle) {
        for (const asset of Object.values(bundle)) {
          if (asset.type === 'asset' && asset.fileName.endsWith('.css') && typeof asset.source === 'string') {
            asset.source = localShadows(asset.source)
          }
        }
      },
    },
  }],
  css: {
    postcss: {
      plugins: [{
        postcssPlugin: 'local-navigation-shadows',
        Declaration(decl: { value: string }) {
          // uni-h5 会注入导航栏阴影与预加载动画；用内嵌渐变去除 CDN 请求。
          decl.value = localShadows(decl.value)
        },
      }],
    },
  },
  // H5 开发模式代理，解决浏览器跨域问题
  server: {
    proxy: {
      "/api": {
        target: "http://localhost:8000",
        changeOrigin: true,
      },
      "/storage": {
        target: "http://localhost:8000",
        changeOrigin: true,
      },
      // 装修默认素材 public/static/diy/*
      "/static": {
        target: "http://localhost:8000",
        changeOrigin: true,
      },
    },
  },
});
