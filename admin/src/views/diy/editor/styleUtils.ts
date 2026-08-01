export interface ComponentStyle {
  margin?: { top: number; right: number; bottom: number; left: number }
  padding?: { top: number; right: number; bottom: number; left: number }
  background?: {
    type?: 'color' | 'gradient' | 'image'
    color?: string
    gradientStart?: string
    gradientEnd?: string
    gradientDirection?: string
    image?: string
  }
  borderRadius?: { topLeft: number; topRight: number; bottomRight: number; bottomLeft: number }
  boxShadow?: { x: number; y: number; blur: number; color: string }
  border?: { width: number; color: string; style: 'solid' | 'dashed' | 'dotted' | 'none' }
  opacity?: number
}

// 大多数组件默认白底；分割线 / 悬浮按钮保持透明（避免画布/端上出现突兀白方块）。
export const TRANSPARENT_BG_TYPES = new Set(['divider', 'float-button'])

export function defaultComponentStyle(type?: string) {
  return {
    margin: { top: 0, right: 0, bottom: 0, left: 0 },
    padding: { top: 0, right: 0, bottom: 0, left: 0 },
    background: {
      type: 'color' as const,
      color: type && TRANSPARENT_BG_TYPES.has(type) ? '' : '#ffffff',
      gradientStart: '#ffffff',
      gradientEnd: '#000000',
      gradientDirection: 'to bottom',
      image: '',
    },
    borderRadius: { topLeft: 0, topRight: 0, bottomRight: 0, bottomLeft: 0 },
    boxShadow: { x: 0, y: 0, blur: 0, color: 'rgba(0,0,0,0.1)' },
    border: {
      width: 0,
      color: '#e0e0e0',
      style: 'solid' as NonNullable<ComponentStyle['border']>['style'],
    },
    opacity: 100,
  }
}

export function componentStyleToCss(style?: ComponentStyle): Record<string, string> {
  const css: Record<string, string> = {}
  if (!style) return css
  const m = style.margin
  if (m) css.margin = `${m.top}px ${m.right}px ${m.bottom}px ${m.left}px`
  const p = style.padding
  if (p) css.padding = `${p.top}px ${p.right}px ${p.bottom}px ${p.left}px`

  const bg = style.background
  if (bg) {
    if (bg.type === 'gradient' && bg.gradientStart && bg.gradientEnd) {
      const dir = bg.gradientDirection || 'to bottom'
      css.background = `linear-gradient(${dir}, ${bg.gradientStart}, ${bg.gradientEnd})`
    } else if (bg.type === 'image' && bg.image) {
      css.backgroundImage = `url("${bg.image}")`
      css.backgroundSize = 'cover'
      css.backgroundPosition = 'center'
    } else if (bg.color) {
      css.backgroundColor = bg.color
    }
  }

  const r = style.borderRadius
  if (r) css.borderRadius = `${r.topLeft}px ${r.topRight}px ${r.bottomRight}px ${r.bottomLeft}px`

  const sh = style.boxShadow
  if (sh && (sh.x !== 0 || sh.y !== 0 || sh.blur !== 0)) css.boxShadow = `${sh.x}px ${sh.y}px ${sh.blur}px ${sh.color}`

  const bd = style.border
  if (bd && bd.width > 0 && bd.style !== 'none') css.border = `${bd.width}px ${bd.style} ${bd.color}`

  if (style.opacity !== undefined && style.opacity < 100) css.opacity = String(style.opacity / 100)

  return css
}

/** 包装层样式：缺省背景时非分割线/悬浮按钮默认白底；显式清空颜色则保持透明 */
export function wrapComponentStyle(type: string, style?: ComponentStyle): Record<string, string> {
  if (type === 'float-button') return {}
  const css = componentStyleToCss(style)
  if (TRANSPARENT_BG_TYPES.has(type)) return css
  const bg = style?.background
  const explicitTransparent =
    !!bg && (bg.type === 'color' || !bg.type) && bg.color === '' && !bg.image
  if (explicitTransparent) return css
  if (!css.backgroundColor && !css.background && !css.backgroundImage) {
    css.backgroundColor = '#ffffff'
  }
  return css
}

export default componentStyleToCss
