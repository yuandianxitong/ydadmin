import { defineConfig } from "vite";
import uni from "@dcloudio/vite-plugin-uni";

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [uni()],
  // Ensure wot-design-uni is properly transpiled
  optimizeDeps: {
    exclude: ["wot-design-uni"],
  },
});
