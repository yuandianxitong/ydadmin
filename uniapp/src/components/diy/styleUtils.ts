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

export const TRANSPARENT_BG_TYPES = new Set(['divider', 'float-button'])

/** componentStyle -> CSS。platform='uniapp' 用 rpx(×2)，否则 px。镜像 admin 转换器逻辑。 */
export function componentStyleToCss(style?: ComponentStyle, platform: 'uniapp' | 'web' = 'uniapp'): Record<string, string> {
  const css: Record<string, string> = {}
  if (!style) return css
  const mul = platform === 'uniapp' ? 2 : 1
  const unit = platform === 'uniapp' ? 'rpx' : 'px'
  const u = (v: number) => `${v * mul}${unit}`
  const m = style.margin
  if (m) css.margin = `${u(m.top)} ${u(m.right)} ${u(m.bottom)} ${u(m.left)}`
  const p = style.padding
  if (p) css.padding = `${u(p.top)} ${u(p.right)} ${u(p.bottom)} ${u(p.left)}`

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
  if (r) css.borderRadius = `${u(r.topLeft)} ${u(r.topRight)} ${u(r.bottomRight)} ${u(r.bottomLeft)}`

  const sh = style.boxShadow
  if (sh && (sh.x !== 0 || sh.y !== 0 || sh.blur !== 0)) css.boxShadow = `${u(sh.x)} ${u(sh.y)} ${u(sh.blur)} ${sh.color}`

  const bd = style.border
  if (bd && bd.width > 0 && bd.style !== 'none') css.border = `${u(bd.width)} ${bd.style} ${bd.color}`

  if (style.opacity !== undefined && style.opacity < 100) css.opacity = String(style.opacity / 100)

  return css
}

/** 包装层样式：缺省背景时非分割线/悬浮按钮默认白底 */
export function wrapComponentStyle(
  type: string,
  style?: ComponentStyle,
  platform: 'uniapp' | 'web' = 'uniapp',
): Record<string, string> {
  if (type === 'float-button') return {}
  const css = componentStyleToCss(style, platform)
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
