// uno.config.ts
import {
    defineConfig,
    presetAttributify,
    presetIcons,
    presetTypography,
    presetWebFonts,
    transformerDirectives,
    transformerVariantGroup,
} from "unocss";
import presetWind3 from '@unocss/preset-wind3'
import { FileSystemIconLoader } from "@iconify/utils/lib/loader/node-loaders";
import fs from "fs";

// 本地SVG图标目录
const iconsDir = "./src/assets/icons";

// 读取本地 SVG 目录，自动生成 safelist
const generateSafeList = () => {
    try {
        return fs
            .readdirSync(iconsDir)
            .filter((file) => file.endsWith(".svg"))
            .map((file) => `i-svg:${file.replace(".svg", "")}`);
    } catch (error) {
        console.error("无法读取图标目录:", error);
        return [];
    }
};

const staticSafeList = [
  'text-primary','bg-primary','border-primary','bg-sidebar','bg-sidebar-active',
  'text-success','bg-success','border-success',
  'text-warning','bg-warning','border-warning',
  'text-danger','bg-danger','border-danger',
  'text-info','bg-info','border-info',
]

export default defineConfig({
    // 只在 content.pipeline.include 中声明文件扫描路径，切勿再保留顶层 include
    content: {
        pipeline: {
            include: [
                './index.html',
                './src/**/*.{vue,js,ts,jsx,tsx}',
            ],
        },
    },

    presets: [
        presetWind3(),
        presetAttributify(),
        presetIcons({
            // 额外属性
            extraProperties: {
                display: "inline-block",
                width: "1em",
                height: "1em",
            },
            // 图表集合
            collections: {
                // svg 是图标集合名称，使用 `i-svg:图标名` 调用
                svg: FileSystemIconLoader(iconsDir, (svg) => {
                    // 如果 `fill` 没有定义，则添加 `fill="currentColor"`
                    return svg.includes('fill="') ? svg : svg.replace(/^<svg /, '<svg fill="currentColor" ');
                }),
            },
        }),
        presetTypography(),
        presetWebFonts({
            fonts: {
                // ...
            },
        }),
    ],

    theme: {
        colors: {
        primary: 'var(--color-primary)',
        'primary-hover': 'var(--color-primary-hover)',
        'primary-active': 'var(--color-primary-active)',
        success: 'var(--color-success-plain)',
        warning: 'var(--color-warning-plain)',
        danger:  'var(--color-danger-plain)',
        info:    'var(--color-info-plain)',

        // 中性色常用别名（按需使用）
        'text-primary':   'var(--color-text-primary)',
        'text-secondary': 'var(--color-text-secondary)',
        'text-tertiary':  'var(--color-text-tertiary)',
        'border':         'var(--color-border)',
        'divider':        'var(--color-divider)',
        'bg':             'var(--color-bg)',
        'surface':        'var(--color-surface)',
        'sidebar':        'var(--color-sidebar-bg)',
        'sidebar-active': 'var(--color-sidebar-active)',
        },
        fontFamily: {
        sans: 'var(--font-family-sans)',
        },
        // 也可扩展自定义屏幕断点与 spacing，但 Uno 原生就很好用了
    },
    shortcuts: [
        // 文字层级
        ['text-display',  'text-[var(--font-size-display)] leading-[var(--line-height-tight)] font-[var(--font-weight-bold)]'],
        ['text-title',    'text-[var(--font-size-title)] leading-[var(--line-height-tight)] font-[var(--font-weight-semibold)]'],
        ['text-subtitle', 'text-[var(--font-size-subtitle)] leading-[var(--line-height-default)] font-[var(--font-weight-medium)]'],
        ['text-body',     'text-[var(--font-size-body)] leading-[var(--line-height-default)] font-[var(--font-weight-regular)]'],
        ['text-caption',  'text-[var(--font-size-caption)] leading-[var(--line-height-default)] text-text-tertiary'],

        // 按钮与卡片
        ['btn',          'px-4 py-2 inline-flex items-center justify-center rounded-[var(--radius-md)] transition-all duration-[var(--motion-duration-base)] ease-[var(--motion-easing)] shadow-[var(--shadow-sm)]'],
        ['btn-primary',  'btn bg-primary text-white hover:opacity-95 active:opacity-85 focus:outline-none focus:ring-2 ring-[var(--color-focus-ring)]'],
        ['card',         'bg-surface border border-[var(--color-border)] rounded-[var(--radius-lg)] shadow-[var(--shadow-md)]'],

        // 帮助类
        ['bg-body',      'bg-[var(--color-bg)]'],
        ['disabled',     'opacity-[var(--opacity-disabled)] pointer-events-none'],
        ['divider',      'h-[1px] bg-[var(--color-divider)]'],
    ],
    safelist: [
        ...staticSafeList,
        ...generateSafeList(),
    ],
    transformers: [transformerDirectives(), transformerVariantGroup()],
})
