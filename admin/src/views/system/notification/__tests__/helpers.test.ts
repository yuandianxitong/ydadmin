import { describe, expect, it } from 'vitest'

import {
    buildNotificationPayload,
    mergeRecipientOptions,
    recipientLabel,
    TARGET_ADMINS,
    TARGET_ALL
} from '../helpers'

const alice = { id: 1, username: 'alice', nickname: '爱丽丝' }
const bob = { id: 2, username: 'bob', nickname: '' }
const carol = { id: 3, username: 'carol', nickname: '卡罗尔' }

describe('mergeRecipientOptions', () => {
    it('keeps the fetched order and dedupes by id', () => {
        const merged = mergeRecipientOptions([], [alice, bob, alice], [])
        expect(merged.map((o) => o.id)).toEqual([1, 2])
    })

    it('keeps a previously loaded selected option the new search did not return', () => {
        const merged = mergeRecipientOptions([alice, bob], [carol], [1])
        expect(merged).toEqual([
            { id: 3, username: 'carol', nickname: '卡罗尔' },
            { id: 1, username: 'alice', nickname: '爱丽丝' }
        ])
    })

    it('adds a placeholder for a selected id that no option knows', () => {
        const merged = mergeRecipientOptions([], [alice], [1, 99])
        expect(merged[1]).toEqual({ id: 99, username: '#99', nickname: '', placeholder: true })
    })

    it('replaces a placeholder once the real option is fetched', () => {
        const withPlaceholder = mergeRecipientOptions([], [], [3])
        const merged = mergeRecipientOptions(withPlaceholder, [carol], [3])
        expect(merged).toEqual([{ id: 3, username: 'carol', nickname: '卡罗尔' }])
    })

    it('does not keep unselected options from the previous search', () => {
        const merged = mergeRecipientOptions([alice, bob], [carol], [])
        expect(merged.map((o) => o.id)).toEqual([3])
    })
})

describe('recipientLabel', () => {
    it('shows nickname with username when a nickname exists', () => {
        expect(recipientLabel(alice)).toBe('爱丽丝（alice）')
    })

    it('shows the username alone when there is no nickname', () => {
        expect(recipientLabel(bob)).toBe('bob')
    })
})

describe('buildNotificationPayload', () => {
    const base = { id: 5, title: 't', content: 'c', type: 1, status: 1 }

    it('drops admin_ids for a broadcast', () => {
        const payload = buildNotificationPayload({
            ...base,
            target_type: TARGET_ALL,
            admin_ids: [1, 2]
        })
        expect(payload).toEqual({ ...base, target_type: TARGET_ALL })
        expect('admin_ids' in payload).toBe(false)
    })

    it('keeps a copy of admin_ids for specified admins', () => {
        const ids = [1, 2]
        const payload = buildNotificationPayload({
            ...base,
            target_type: TARGET_ADMINS,
            admin_ids: ids
        })
        expect(payload.admin_ids).toEqual([1, 2])
        expect(payload.admin_ids).not.toBe(ids)
    })

    it('sends an empty list when specified admins has none (backend answers 422)', () => {
        const payload = buildNotificationPayload({ ...base, target_type: TARGET_ADMINS })
        expect(payload.admin_ids).toEqual([])
    })

    // 控制器裁决：编辑时详情把全部收件人（含超出数据范围 / 已删除的管理员）都回填进来，
    // 原样提交会被后端 422 拒绝；只有编辑者真的改动了收件人选择，才带上 admin_ids。
    it('omits admin_ids on update when the selection did not change (order-independent)', () => {
        const payload = buildNotificationPayload(
            { ...base, target_type: TARGET_ADMINS, admin_ids: [2, 1] },
            [1, 2]
        )
        expect('admin_ids' in payload).toBe(false)
    })

    it('includes admin_ids on update when the selection changed', () => {
        const payload = buildNotificationPayload(
            { ...base, target_type: TARGET_ADMINS, admin_ids: [1, 3] },
            [1, 2]
        )
        expect(payload.admin_ids).toEqual([1, 3])
    })

    it('always includes admin_ids on create for specified admins (no original selection to compare)', () => {
        const payload = buildNotificationPayload(
            { ...base, id: undefined, target_type: TARGET_ADMINS, admin_ids: [1, 2] },
            undefined
        )
        expect(payload.admin_ids).toEqual([1, 2])
    })

    it('never includes admin_ids for a broadcast even when an original selection is given', () => {
        const payload = buildNotificationPayload(
            { ...base, target_type: TARGET_ALL, admin_ids: [1, 2] },
            [1, 2]
        )
        expect('admin_ids' in payload).toBe(false)
    })
})
