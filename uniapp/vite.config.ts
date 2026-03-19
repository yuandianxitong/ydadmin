import { defineConfig } from "vite";
import uniModule from "@dcloudio/vite-plugin-uni";
import UnoCSS from "unocss/vite";

// CJS interop: @dcloudio/vite-plugin-uni uses exports.default in CJS
const uni = (uniModule as any).default || uniModule;

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [UnoCSS(), uni()],
  // Ensure wot-design-uni is properly transpiled
  optimizeDeps: {
    exclude: ["wot-design-uni"],
  },
});
