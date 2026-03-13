<script lang="ts">
import * as ElementPlusIcons from '@element-plus/icons-vue'
import { ElIcon } from 'element-plus'
import { createVNode, defineComponent, h } from 'vue'

export const EL_ICON_PREFIX = 'el-icon-'
export const LOCAL_ICON_PREFIX = 'i-svg:'

// 构建 ElementPlus 图标的映射表：前缀名 => 组件
const elIconMap: Record<string, any> = {}
Object.values(ElementPlusIcons).forEach((comp: any) => {
    elIconMap[`${EL_ICON_PREFIX}${comp.name}`] = comp
})

export default defineComponent({
    name: 'Icon',
    props: {
        name: { type: String, required: true },
        size: { type: [String, Number], default: '1em' },
        color: { type: String, default: 'inherit' }
    },
    setup(props) {
        return () => {
            const { name, size, color } = props
            if (typeof name !== 'string') return null

            // 1. ElementPlus 官方图标
            const ElComp = elIconMap[name]
            if (ElComp) {
                return createVNode(ElIcon, { size, color }, { default: () => createVNode(ElComp) })
            }

            // 2. 本地 SVG（由 UnoCSS presetIcons 处理）
            if (name.startsWith(LOCAL_ICON_PREFIX)) {
                return h('i', {
                    class: name,
                    style: {
                        fontSize: typeof size === 'number' ? `${size}px` : size,
                        color
                    }
                })
            }

            return null
        }
    }
})
</script>
