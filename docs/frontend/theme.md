# 主题与样式

## 概述

本项目采用多层主题系统：

1. **Design Token（CSS 变量）** -- 统一的颜色、字体、间距等基础变量
2. **Element Plus 主题定制** -- 覆盖 Element Plus 默认样式变量
3. **UnoCSS 原子化工具类** -- 快速编写样式
4. **SCSS** -- 全局基础样式
5. **暗色模式** -- 支持 `light` / `dark` / `system` 三种模式

## Design Token

设计令牌定义在 `src/theme/tokens.scss` 中，基于 CSS 变量实现：

### 品牌色

```css
:root {
    --color-brand:    #0b51b7;    /* 主色 */
    --color-accent:   #0aa0b7;    /* 辅助强调色 */
    --color-success:  #18a058;
    --color-warning:  #f0a020;
    --color-danger:   #d03050;
    --color-info:     #909399;
}
```

### 中性色

```css
:root {
    --color-bg:             #ffffff;    /* 页面背景 */
    --color-surface:        #ffffff;    /* 卡片/容器背景 */
    --color-border:         var(--gray-300);
    --color-text-primary:   #1f2328;
    --color-text-secondary: #4b5563;
    --color-text-tertiary:  #6b7280;
}
```

### 字体系统

```css
:root {
    --font-size-display:  32px;
    --font-size-title:    24px;
    --font-size-subtitle: 18px;
    --font-size-body:     14px;
    --font-size-caption:  12px;
}
```

## 主题切换

通过 `src/theme/apply.ts` 实现多主题切换，内置 5 种预设主题：

```ts
type ThemeName = 'blue' | 'cyan' | 'green' | 'orange' | 'purple'

// 切换主题
applyTheme('cyan')

// 动态覆盖单个 Token
setToken('--color-brand', '#ff6600')
```

主题通过 `data-theme` 属性切换，`blue` 为默认主题使用 `:root` 规则，其他主题使用 `[data-theme="cyan"]` 等选择器覆盖变量。

## Element Plus 主题定制

Element Plus 样式覆盖文件位于 `src/theme/overrides/` 目录。通过 CSS 变量映射，Token 变化时 Element Plus 组件样式自动跟随：

```css
:root {
    --color-primary-hover:  var(--el-color-primary-light-3);
    --color-primary-active: var(--el-color-primary-dark-2);
}
```

`settingStore` 中的 `setTheme()` 方法会同时更新 Element Plus 的颜色变量：

```ts
setTheme({
    primary: this.theme,
    success: this.successTheme,
    warning: this.warningTheme,
    danger:  this.dangerTheme,
    error:   this.errorTheme,
    info:    this.infoTheme
}, isDark)
```

## UnoCSS 使用

项目使用 UnoCSS 作为原子化 CSS 引擎，配置文件为 `admin/uno.config.ts`。

### 启用的预设

| 预设 | 说明 |
|------|------|
| `presetWind3` | 兼容 Tailwind CSS 的工具类 |
| `presetAttributify` | 属性模式（在 HTML 属性中写工具类） |
| `presetIcons` | 纯 CSS 图标，支持本地 SVG |
| `presetTypography` | 排版工具类 |

### 自定义快捷方式

```ts
// UnoCSS shortcuts
shortcuts: [
    ['text-title',   'text-[var(--font-size-title)] leading-[var(--line-height-tight)] font-semibold'],
    ['text-body',    'text-[var(--font-size-body)] leading-[var(--line-height-default)]'],
    ['btn-primary',  'btn bg-primary text-white hover:opacity-95'],
    ['card',         'bg-surface border border-[var(--color-border)] rounded-lg shadow-md'],
    ['disabled',     'opacity-[var(--opacity-disabled)] pointer-events-none'],
]
```

### 使用示例

```vue
<template>
    <!-- 工具类写法 -->
    <div class="flex items-center gap-4 p-4 card">
        <span class="text-title text-primary">标题</span>
        <span class="text-body text-text-secondary">正文描述</span>
    </div>

    <!-- 属性模式（attributify） -->
    <div flex items-center gap-4 p-4>
        <span text-lg font-bold>内容</span>
    </div>
</template>
```

### 本地 SVG 图标

SVG 图标放置在 `src/assets/icons/` 目录，通过 `i-svg:图标名` 使用：

```vue
<span class="i-svg:dashboard" />
<span class="i-svg:settings" />
```

## SCSS 变量与全局样式

全局基础样式定义在 `src/styles/` 目录：

- `base.scss` -- 重置样式、基础排版
- `index.scss` -- 样式入口文件

```scss
// base.scss 设置全局字体和盒模型
body {
    font-family: "Inter", sans-serif;
    -webkit-font-smoothing: antialiased;
}

*, ::before, ::after {
    box-sizing: border-box;
}
```

## 暗色模式

支持三种模式，通过 `settingStore.themeMode` 控制：

| 模式 | 说明 |
|------|------|
| `light` | 强制浅色模式 |
| `dark` | 强制暗色模式 |
| `system` | 跟随系统偏好，并监听 `prefers-color-scheme` 变化 |

### 切换暗色模式

```ts
const settingStore = useSettingStore()

// 设置主题模式
settingStore.setSetting({ key: 'themeMode', value: 'dark' })
settingStore.applyThemeMode()

// 恢复默认
settingStore.resetTheme()
```

暗色模式通过以下步骤生效：

1. `applyDarkClass(isDark)` -- 在 `<html>` 添加/移除 `dark` class
2. `applyDarkTheme(darkTheme)` -- 应用暗色主题色值
3. `setTheme(colors, isDark)` -- 重新计算 Element Plus 主题色

## 国际化（vue-i18n）

### 配置

国际化初始化在 `src/locales/setupI18n.ts` 中：

```ts
const i18n = createI18n({
    legacy: false,           // 使用 Composition API
    locale: 'zh-CN',         // 默认语言
    messages: {
        'zh-CN': zhCN,
        'en-US': enUS
    }
})
```

### 支持的语言

| 语言 | 代码 | 语言文件 |
|------|------|----------|
| 简体中文 | `zh-CN` | `src/locales/zh-CN.ts` |
| English | `en-US` | `src/locales/en-US.ts` |

### 切换语言

```ts
import { setLocale } from '@/locales/setupI18n'

// 切换到英文
setLocale('en-US')
```

语言切换时会自动：
1. 更新 vue-i18n 的 locale
2. 保存到本地缓存
3. 同步设置 `think_lang` Cookie（供 ThinkPHP 后端读取）

### 在模板中使用

```vue
<template>
    <span>{{ $t('common.confirm') }}</span>
    <el-button>{{ $t('common.cancel') }}</el-button>
</template>
```

### 在脚本中使用

```ts
import { t } from '@/utils/i18n'

ElMessage.success(t('common.operationSuccess'))
```

### 前后端语言同步

请求拦截器会自动在每个请求头中添加 `think-lang`，确保后端 ThinkPHP 返回对应语言的响应消息。
