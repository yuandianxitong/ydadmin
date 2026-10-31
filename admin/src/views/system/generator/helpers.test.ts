import { describe, expect, it } from 'vitest'

import { modelFromTable } from './helpers'

describe('生成器模型命名与 CLI 一致', () => {
    it.each([
        ['gen_articles', '', 'GenArticle'],
        ['biz_tickets', '', 'BizTicket'],
        ['yd_gen_articles', 'yd_', 'GenArticle'],
        ['gen_articles', 'yd_', 'GenArticle'],
        ['address', '', 'Address']
    ])('%s 仅移除指定表前缀', (table, prefix, expected) => {
        expect(modelFromTable(table, prefix)).toBe(expected)
    })
})
