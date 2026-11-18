import { describe, expect, it } from 'vitest'
import { draftRequest, type ReplenishmentRow } from '../../shared/replenishment'

describe('replenishment review', () => {
    it('sends reviewed fingerprints and stable request IDs instead of client pricing', () => {
        const result = draftRequest(
            [{ id: 1, fingerprint: 'a'.repeat(64), can_create_draft: true, unit_cost: '0.10' } as ReplenishmentRow],
            'request-id',
        )
        expect(result).toEqual({ request_id: 'request-id', product_ids: [1], expected: { 1: 'a'.repeat(64) } })
        expect(result).not.toHaveProperty('unit_cost')
    })
    it('rejects blocked, empty and oversized selections', () => {
        expect(() => draftRequest([], 'id')).toThrow('eligible')
        expect(() => draftRequest([{ id: 1, can_create_draft: false } as ReplenishmentRow], 'id')).toThrow('eligible')
        expect(() =>
            draftRequest(
                Array.from({ length: 101 }, (_, id) => ({ id, can_create_draft: true }) as ReplenishmentRow),
                'id',
            ),
        ).toThrow('100')
    })
})
