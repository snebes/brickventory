import { describe, expect, it } from 'vitest'
import { stockLevel, reorderLink, money, type DashboardProduct } from '../../shared/dashboard'
import { inventoryProxyPath } from '../../shared/inventoryProxy'

describe('dashboard contracts', () => {
    it('keeps stock rulers bounded without losing the actual quantity', () => {
        expect(stockLevel({ stock: -3, min_stock: 10 })).toEqual({ stock: -3, minimum: 10, shortfall: 13, percent: 0 })
        expect(stockLevel({ stock: 20, min_stock: 10 }).percent).toBe(100)
        expect(stockLevel({ stock: 0, min_stock: 0 }).percent).toBe(0)
    })
    it('opens a reviewable purchase draft with the suggestion currency and unit cost', () => {
        const link = reorderLink({
            id: 1,
            stock: 2,
            min_stock: 5,
            suggested_quantity: 3,
            supplier_id: 4,
            unit_cost: '0.75',
            currency: 'EUR',
        } as DashboardProduct)
        const query = new URL(link, 'http://localhost').searchParams
        expect(query.get('product_id')).toBe('1')
        expect(query.get('supplier_id')).toBe('4')
        expect(query.get('quantity')).toBe('3')
        expect(query.get('currency')).toBe('EUR')
        expect(query.get('unit_cost')).toBe('0.75')
        expect(
            new URL(
                reorderLink({ id: 1, stock: 0, min_stock: 1, has_variants: true } as DashboardProduct),
                'http://localhost',
            ).searchParams.get('variant_required'),
        ).toBe('1')
    })
    it('permits only the read dashboard path through the existing resource proxy', () => {
        expect(inventoryProxyPath('dashboard')).toBe('dashboard')
        expect(inventoryProxyPath('dashboard/../products')).toBeNull()
        expect(money('1.25', 'USD')).toBe('$1.25')
        expect(money('1.25', 'EUR')).toBe('€1.25')
    })
})
