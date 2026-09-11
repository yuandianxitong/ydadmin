import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * 通用下拉选项常量
 *
 * 状态、是否等高频复用的选项集中定义在此，避免各列表页/表单组件内联重复。
 * 所有选项 label 通过 i18n computed 生成，支持多语言切换。
 *
 * 使用示例：
 * ```vue
 * <script setup>
 * import { useStatusOptions } from '@/constants/options'
 * const { statusOptions, statusAllOptions } = useStatusOptions()
 * </script>
 *
 * <template>
 *   <el-select v-model="searchForm.status">
 *     <el-option v-for="opt in statusAllOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
 *   </el-select>
 * </template>
 * ```
 */
export function useStatusOptions() {
    const { t } = useI18n()

    /** 启用/禁用 —— 用于 Form 组件的 radio 或 select（不含"全部"）*/
    const statusOptions = computed(() => [
        { label: t('common.enable'), value: 1 },
        { label: t('common.disable'), value: 0 }
    ])

    /** 启用/禁用 + 全部 —— 用于搜索表单的 select */
    const statusAllOptions = computed(() => [
        { label: t('common.all'), value: '' },
        { label: t('common.enable'), value: 1 },
        { label: t('common.disable'), value: 0 }
    ])

    /** 是/否 */
    const yesNoOptions = computed(() => [
        { label: t('common.yes'), value: 1 },
        { label: t('common.no'), value: 0 }
    ])

    return {
        statusOptions,
        statusAllOptions,
        yesNoOptions
    }
}

/** 状态值常量 */
export const STATUS = {
    ENABLED: 1,
    DISABLED: 0
} as const
