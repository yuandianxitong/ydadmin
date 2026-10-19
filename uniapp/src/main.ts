import 'virtual:uno.css'
import { createSSRApp } from 'vue'
import { createPinia } from 'pinia'
import uviewPlus, { fontUtil } from 'uview-plus'
import App from './App.vue'

export function createApp() {
  uni.setLocale('zh-Hans')
  // 内置图标使用随包 CSS 字体，避免组件再次请求远程字体。
  fontUtil.params.loaded = true
  const app = createSSRApp(App)
  const pinia = createPinia()
  app.use(pinia)
  app.use(uviewPlus)
  return { app }
}
