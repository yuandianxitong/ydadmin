export interface DiyPagePayload {
  components: any[]
  page_settings: Record<string, any>
  title?: string
}

/** 判定 member 装修数据是否可渲染（已发布且有可见组件） */
export function hasRenderableDecoration(page: DiyPagePayload | null | undefined): boolean {
  if (!page || !Array.isArray(page.components)) return false
  return page.components.filter((c) => !c?.hidden).length > 0
}
