export interface VisibleFilterable {
  hidden?: boolean
  [key: string]: any
}

/** 过滤掉 hidden 组件；对空值/非数组容错，返回空数组。 */
export const filterVisible = <T extends VisibleFilterable>(list?: T[] | null): T[] =>
  (list || []).filter((c) => !c.hidden)
