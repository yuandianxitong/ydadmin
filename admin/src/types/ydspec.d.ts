export interface YdRelation { to: string; kind: 'belongsTo' | 'hasMany' }
export interface YdField {
  name: string
  type: 'string' | 'text' | 'int' | 'bigint' | 'decimal' | 'boolean' | 'datetime' | 'date' | 'enum' | 'json'
  length?: number
  precision?: number
  scale?: number
  nullable?: boolean
  unique?: boolean
  index?: boolean
  default?: unknown
  enum?: string[]
  relation?: YdRelation
}
export interface YdIndex { fields: string[]; type: 'index' | 'unique' }
export interface YdEntity {
  name: string
  table: string
  kind: 'business' | 'log'
  soft_delete: 'soft' | 'none'
  fields: YdField[]
  indexes?: YdIndex[]
}
export interface YdSpec {
  version: 'ydspec/v1'
  module: { name: string; title: string }
  entities: YdEntity[]
}
export interface SpecQuestion { id: string; text: string; why?: string; kind?: 'text' | 'choice' | 'boolean'; options?: string[] }
export interface SpecExplanation { ref: string; rationale?: string; source_rule?: string; deletable?: boolean; risk?: string }
export interface SpecIssue { ref: string; rule: string; severity: 'error' | 'warn'; message: string }
