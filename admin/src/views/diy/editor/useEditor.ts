import { computed, ref } from 'vue'

import type { DiyComponent } from '@/api/diy'

import { defaultComponentStyle } from './styleUtils'

export const WIDGET_TYPES = [
    'banner',
    'nav-grid',
    'category-nav',
    'rich-text',
    'title-bar',
    'divider',
    'image-ad',
    'image-cube',
    'video',
    'notice',
    'search-bar',
    'float-button',
    'user-info-card',
    'service-menu',
    'content-list'
] as const

export const TYPE_LABELS: Record<string, string> = {
    banner: '轮播图',
    'nav-grid': '图文导航',
    'category-nav': '分类导航',
    'rich-text': '富文本',
    'title-bar': '标题栏',
    divider: '辅助分割',
    'image-ad': '图片广告',
    'image-cube': '图片魔方',
    video: '视频',
    notice: '公告',
    'search-bar': '搜索框',
    'float-button': '悬浮按钮',
    'user-info-card': '用户信息卡',
    'service-menu': '服务菜单',
    'content-list': '内容列表'
}

export function genId(): string {
    return 'c_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6)
}

export function defaultAssets() {
    return [
        { label: '余额', stat_key: 'user.balance', link: '/modules/user/pages/balance' },
        { label: '积分', stat_key: 'user.points', link: '/modules/user/pages/points' }
    ]
}

/** 存量数据兜底：选中 user-info-card 时就地补 assets（与 StyleConfig.ensureFull 同思路） */
export function ensureUserInfoAssets(c: { type: string; props: Record<string, any> } | null) {
    if (c?.type === 'user-info-card' && !Array.isArray(c.props.assets)) c.props.assets = defaultAssets()
}

export function defaultProps(type: string): Record<string, any> {
    const base: Record<string, Record<string, any>> = {
        // banner.height 单位是 rpx（uniapp 直接使用，PC/画布 ÷2 显示）：300rpx = 实际显示 150px
        banner: { items: [], autoplay: true, interval: 3000, height: 300 },
        'nav-grid': { items: [], columns: 4 },
        // 分类导航（对齐元点Shop 实际渲染）：style=icon-grid 图标网格 / scroll 横向滚动；
        // rows 仅决定空数据占位数量（真实 items 按 columns 自动换行）
        'category-nav': { style: 'icon-grid', items: [], rows: 2, columns: 5 },
        'rich-text': { content: '' },
        'title-bar': { title: '', subtitle: '', align: 'left', more_text: '', more_link: '' },
        divider: { type: 'line', height: 20, color: '#eeeeee' },
        'image-ad': { items: [], layout: 'single', columns: 2 },
        'image-cube': { items: [], cols: 2, gap: 0 },
        video: { src: '', poster: '', autoplay: false, loop: false, height: 300 },
        notice: { items: [], speed: 3000, icon: '' },
        'search-bar': { placeholder: '搜索', radius: 20, bg_color: '#f5f5f5', link: '' },
        'float-button': { items: [], position: 'right-bottom' },
        'user-info-card': { show_assets: true, assets: defaultAssets() },
        'service-menu': { items: [] },
        'content-list': {
            section_title: '最新文章',
            source: 'latest',
            category_id: 0,
            limit: 6,
            layout: 'list',
            show_cover: true,
            show_summary: true,
            show_date: true,
            more_link: '/pages/discover/index'
        }
    }
    return { ...(base[type] || {}), componentStyle: defaultComponentStyle(type) }
}

export function useEditor() {
    const components = ref<DiyComponent[]>([])
    const pageSettings = ref<Record<string, any>>({ background_color: '' })
    const selectedId = ref<string | null>(null)
    const undoStack = ref<string[]>([])
    const redoStack = ref<string[]>([])
    const LIMIT = 50

    const selected = computed(() => components.value.find((c) => c.id === selectedId.value) || null)

    function snapshot(): string {
        return JSON.stringify({ components: components.value, pageSettings: pageSettings.value })
    }

    function pushUndo() {
        undoStack.value.push(snapshot())
        if (undoStack.value.length > LIMIT) undoStack.value.shift()
        redoStack.value = []
    }

    function apply(s: string) {
        const o = JSON.parse(s)
        components.value = o.components
        pageSettings.value = o.pageSettings
    }

    function setState(comps: DiyComponent[], settings: Record<string, any>) {
        components.value = Array.isArray(comps) ? comps : []
        pageSettings.value = settings || { background_color: '' }
        undoStack.value = []
        redoStack.value = []
        selectedId.value = null
    }

    function addWidget(type: string, presetProps?: Record<string, any>) {
        pushUndo()
        const base = presetProps ?? defaultProps(type)
        const props = { ...base, componentStyle: base.componentStyle ?? defaultComponentStyle(type) }
        const c: DiyComponent = { id: genId(), type, props }
        // 有选中：插入到选中项下方；无选中：追加到列表最底部
        const idx = selectedId.value
            ? components.value.findIndex((x) => x.id === selectedId.value)
            : -1
        if (idx >= 0) {
            components.value.splice(idx + 1, 0, c)
        } else {
            components.value.push(c)
        }
        selectedId.value = c.id
    }

    /** 上移(dir=-1)/下移(dir=1)某组件 */
    function move(id: string, dir: -1 | 1) {
        const idx = components.value.findIndex((c) => c.id === id)
        if (idx < 0) return
        const target = idx + dir
        if (target < 0 || target >= components.value.length) return
        pushUndo()
        const arr = components.value
        const [item] = arr.splice(idx, 1)
        arr.splice(target, 0, item)
    }

    function remove(id: string) {
        pushUndo()
        components.value = components.value.filter((c) => c.id !== id)
        if (selectedId.value === id) selectedId.value = null
    }

    function duplicate(id: string) {
        const idx = components.value.findIndex((c) => c.id === id)
        if (idx === -1) return
        pushUndo()
        const source = components.value[idx]
        const copy: DiyComponent = {
            ...JSON.parse(JSON.stringify(source)),
            id: genId()
        }
        components.value.splice(idx + 1, 0, copy)
        selectedId.value = copy.id
    }

    function toggleHidden(id: string) {
        const comp = components.value.find((c) => c.id === id)
        if (!comp) return
        pushUndo()
        comp.hidden = !comp.hidden
    }

    function reorder(ids: string[]) {
        pushUndo()
        const map = new Map(components.value.map((c) => [c.id, c]))
        components.value = ids.map((id) => map.get(id)).filter(Boolean) as DiyComponent[]
    }

    function select(id: string) {
        selectedId.value = id || null
    }

    /**
     * 在「即将修改某组件属性/样式」之前调用，捕获修改前快照用于撤销。
     * 必须在 mutation 之前（如绑定到输入控件的 @focus，而非 @change）。
     */
    function beginChange() {
        pushUndo()
    }

    function undo() {
        if (!undoStack.value.length) return
        redoStack.value.push(snapshot())
        apply(undoStack.value.pop() as string)
    }

    function redo() {
        if (!redoStack.value.length) return
        undoStack.value.push(snapshot())
        apply(redoStack.value.pop() as string)
    }

    return {
        components,
        pageSettings,
        selectedId,
        selected,
        undoStack,
        redoStack,
        setState,
        addWidget,
        duplicate,
        toggleHidden,
        move,
        remove,
        reorder,
        select,
        beginChange,
        undo,
        redo
    }
}

/** 仅个人中心（member）页可用的内置组件 */
export const MEMBER_ONLY_TYPES = ['user-info-card', 'service-menu'] as const
