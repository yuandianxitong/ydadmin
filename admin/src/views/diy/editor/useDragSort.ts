import { ref } from 'vue'

/**
 * 属性面板项目卡片的拖拽排序（原生 HTML5 drag，对齐 Shop 编辑器模式）。
 * 撤销时序：drop 时先 begin（捕获重排前快照）再就地 splice 重排。
 * 用法：卡片上绑 draggable="true" @dragstart="onDragStart(i, $event)"
 *       @dragover.prevent @drop="onDrop(items, i)" @dragend="reset()"
 */
export function useDragSort(begin: () => void) {
    const dragIdx = ref<number | null>(null)

    function onDragStart(i: number, e: DragEvent) {
        dragIdx.value = i
        if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move'
    }

    function onDrop(items: unknown[], i: number) {
        if (dragIdx.value === null || dragIdx.value === i) {
            dragIdx.value = null
            return
        }
        begin()
        const [item] = items.splice(dragIdx.value, 1)
        items.splice(i, 0, item)
        dragIdx.value = null
    }

    function reset() {
        dragIdx.value = null
    }

    return { dragIdx, onDragStart, onDrop, reset }
}
