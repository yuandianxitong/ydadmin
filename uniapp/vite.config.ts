import { defineConfig } from "vite";
import uniModule from "@dcloudio/vite-plugin-uni";
import UnoCSS from "unocss/vite";

// CJS interop: @dcloudio/vite-plugin-uni uses exports.default in CJS
const uni = (uniModule as any).default || uniModule;

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [UnoCSS(), uni()],
  // H5 开发模式代理，解决浏览器跨域问题
  server: {
    proxy: {
      "/api": {
        target: "http://localhost:8005",
        changeOrigin: true,
      },
      "/storage": {
        target: "http://localhost:8005",
        changeOrigin: true,
      },
    },
  },
});
