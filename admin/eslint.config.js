import pluginVue from 'eslint-plugin-vue'
import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript'
import pluginPrettier from '@vue/eslint-config-prettier'
import simpleImportSort from 'eslint-plugin-simple-import-sort'
import autoImportGlobals from './.eslintrc-auto-import.json' with { type: 'json' }

export default defineConfigWithVueTs(
    // ========== 全局忽略 ==========
    {
        ignores: ['dist/', 'node_modules/', 'auto-imports.d.ts', 'components.d.ts', '.vscode/', '.idea/']
    },

    // ========== Vue 推荐规则（从 essential → recommended） ==========
    ...pluginVue.configs['flat/recommended'],

    // ========== TypeScript 推荐规则 ==========
    vueTsConfigs.recommended,

    // ========== Auto-import 全局变量 ==========
    {
        languageOptions: {
            globals: autoImportGlobals.globals
        }
    },

    // ========== 项目自定义规则 ==========
    {
        plugins: {
            'simple-import-sort': simpleImportSort
        },
        rules: {
            // ---- 导入排序 ----
            'simple-import-sort/imports': 'error',

            // ---- Vue 规则 ----
            // 允许单词组件名（index.vue, App.vue 等常见场景）
            'vue/multi-word-component-names': 'off',
            // 强制 v-bind/v-on 缩写一致性
            'vue/v-bind-style': ['warn', 'shorthand'],
            'vue/v-on-style': ['warn', 'shorthand'],
            // 强制 defineEmits 类型写法
            'vue/define-emits-declaration': ['warn', 'type-based'],
            // 强制 defineProps 类型写法
            'vue/define-props-declaration': ['warn', 'type-based'],
            // 禁止未使用的 ref（常见 bug 源）
            'vue/no-unused-refs': 'warn',
            // 属性排序（warn，渐进修复）
            'vue/attributes-order': 'warn',
            // 以下规则与 Prettier 冲突或过于严格，关闭
            'vue/max-attributes-per-line': 'off',
            'vue/singleline-html-element-content-newline': 'off',
            'vue/first-attribute-linebreak': 'off',
            'vue/html-indent': 'off',
            'vue/html-closing-bracket-newline': 'off',
            // TypeScript 定义的 optional props 已有类型约束，无需强制默认值
            'vue/require-default-prop': 'off',
            // 允许 v-html（编辑器/富文本场景需要）
            'vue/no-v-html': 'off',

            // ---- TypeScript 规则 ----
            // any 类型：warn 而非 error，给渐进式修复空间
            '@typescript-eslint/no-explicit-any': 'warn',
            // ts-ignore 必须带说明
            '@typescript-eslint/ban-ts-comment': ['warn', {
                'ts-ignore': 'allow-with-description',
                'ts-expect-error': 'allow-with-description',
                'ts-nocheck': 'allow-with-description'
            }],
            // 未使用变量（忽略以 _ 开头的参数）
            '@typescript-eslint/no-unused-vars': ['warn', {
                argsIgnorePattern: '^_',
                varsIgnorePattern: '^_',
                caughtErrorsIgnorePattern: '^_'
            }],
            // 非空断言 warn（提示风险但不阻塞）
            '@typescript-eslint/no-non-null-assertion': 'warn',
            // 可选链后的非空断言（几乎总是 bug）
            '@typescript-eslint/no-non-null-asserted-optional-chain': 'error',
            // 空函数允许（事件处理器占位等场景）
            '@typescript-eslint/no-empty-function': 'off',
            // 禁止 require（统一 ESM）
            '@typescript-eslint/no-require-imports': 'warn',

            // ---- JavaScript 基础规则 ----
            // no-undef 关闭（TypeScript 自身检查 + auto-imports）
            'no-undef': 'off',
            // 禁止 console.log（warn 级别，调试后应清理）
            'no-console': ['warn', { allow: ['warn', 'error'] }],
            // 禁止 debugger
            'no-debugger': 'warn',
            // 禁止 prototype 内建方法直接调用
            'no-prototype-builtins': 'warn',
            // 优先 const
            'prefer-const': ['warn', { destructuring: 'all' }],
            // 禁止不必要的模板字符串
            'no-useless-concat': 'warn',
            // 禁止 var
            'no-var': 'error',
            // 对象简写
            'object-shorthand': ['warn', 'properties'],
            // eqeqeq
            'eqeqeq': ['warn', 'smart'],
            // prefer-spread 关闭（部分场景 apply 更清晰）
            'prefer-spread': 'off',
            // 禁止多余的 return await
            'no-return-await': 'warn'
        }
    },

    // ========== Prettier（放最后，覆盖格式类规则） ==========
    pluginPrettier
)
