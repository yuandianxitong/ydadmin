/**
 * UniApp 全局主题（开发者约定）
 *
 * 样式里用 CSS 变量（运行时由后台「主题风格」下发）：
 *   color: var(--yd-color-primary);
 *   background: linear-gradient(135deg, var(--yd-color-primary), var(--yd-color-primary-dark));
 *
 * 变量一览：
 *   --yd-color-primary       主色（导航/按钮/选中态）
 *   --yd-color-primary-dark  深色 / 渐变终点
 *   --yd-color-primary-soft  主色浅底（约 10% 透明）
 *   --yd-color-price         价格强调
 *   --yd-color-page-bg       页面背景
 *   --yd-color-button-text   主按钮文字
 *   --yd-color-badge         角标 / 警示
 *
 * 脚本里：
 *   const { primary, colors, cssVars, applyNavBar } = useTheme()
 *
 * 带原生导航栏的页面建议在 onShow 中调用 applyNavBar()。
 * 新增页面请使用上述变量，勿再写死 #2979ff。
 */
import { computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { useMobileConfigStore } from '@/store/mobile-config.store'

export function useTheme() {
  const store = useMobileConfigStore()
  return {
    primary: computed(() => store.themeColor),
    colors: computed(() => store.themeColors),
    cssVars: computed(() => store.themeCssVars),
    applyNavBar: () => store.applyNavigationBarTheme(),
  }
}

/** 在页面 setup 顶层调用：返回主题能力，并在 onShow 时同步原生导航栏颜色 */
export function useThemePage() {
  const theme = useTheme()
  onShow(() => theme.applyNavBar())
  return theme
}
