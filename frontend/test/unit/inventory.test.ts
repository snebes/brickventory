import { describe, expect, it } from 'vitest'
import { formPayload, formValues, linePayload, workflowActions, canEdit } from '../../utils/inventory'
import { inventoryResources } from '../../shared/inventory'

describe('Symfony inventory form contracts', () => {
    it('preserves money strings and converts identifiers and booleans', () => {
        const fields = inventoryResources.products!.fields
        const values = {
            ...formValues(fields),
            name: 'Brick',
            sku: 'PART',
            price: '0.10',
            cost: '0.00',
            min_stock: '3',
            category_id: '2',
            options: '{"color":["red"]}',
        }
        expect(formPayload(fields, values)).toMatchObject({
            price: '0.10',
            min_stock: 3,
            category_id: 2,
            is_active: true,
            options: { color: ['red'] },
        })
        expect(formPayload(fields, values)).not.toHaveProperty('stock')
    })
    it('rejects fractional quantities and invalid JSON before submitting', () => {
        expect(() => linePayload('orders', [{ product_id: 1, quantity: 1.5, unit_price: '1.00' }])).toThrow(
            'whole-number',
        )
        expect(() =>
            formPayload(inventoryResources.variants!.fields, {
                name: 'Red',
                sku: 'RED',
                parent_id: 1,
                attributes: '{',
            }),
        ).toThrow('valid JSON')
    })
    it('uses purchase unit_cost and omits sale discount fields from receipts', () => {
        expect(
            linePayload('purchase-orders', [
                {
                    product_id: '1',
                    product_variant_id: '2',
                    quantity: 5,
                    unit_price: '0.75',
                    tax: '5',
                    discount_value: '7',
                    supplier_sku: 'SUP',
                    serialText: 'A\nB',
                },
            ]),
        ).toEqual([
            {
                product_id: 1,
                product_variant_id: 2,
                quantity: 5,
                unit_cost: '0.75',
                supplier_sku: 'SUP',
                serials: ['A', 'B'],
            },
        ])
    })
    it('counts absolute quantities including zero without sending financial fields', () => {
        expect(linePayload('stock-audits', [{ product_id: 1, quantity: 0, unit_price: '5.00', tax: '2.00' }])).toEqual([
            { product_id: 1, quantity: 0 },
        ])
    })
    it('retains traceability while omitting internal editor state', () => {
        expect(
            linePayload('stock-transfers', [
                {
                    id: 44,
                    product_id: 1,
                    quantity: 2,
                    batch: 'B',
                    serialText: ' A \n\n B ',
                    received_quantity: 20,
                    expires_at: '2030-01-01',
                },
            ]),
        ).toEqual([{ product_id: 1, quantity: 2, batch: 'B', serials: ['A', 'B'], expires_at: '2030-01-01' }])
    })
    it('does not offer receipt or line edits on completed documents', () => {
        expect(workflowActions('purchase-orders', { id: 1, status: 'received' })).toEqual([])
        expect(canEdit('orders', { id: 1, status: 'shipped' })).toBe(false)
        expect(canEdit('stock-audits', { id: 1, status: 'draft' })).toBe(false)
    })
    it('gates actions while approval is pending', () => {
        expect(
            workflowActions('orders', { id: 1, status: 'pending', details: { approval_status: 'pending' } }),
        ).toEqual(['approve', 'reject', 'cancel'])
    })
})

describe('inventory proxy path restrictions', () => {
    it('allows only inventory resources without traversal', async () => {
        const { inventoryProxyPath } = await import('../../shared/inventoryProxy')
        expect(inventoryProxyPath('products/1/attachments/2')).toBe('products/1/attachments/2')
        for (const path of [
            '../admin',
            'products//1',
            'products/%2e%2e',
            'admin',
            'https://attacker.invalid',
            'barcode-lookup/%2fadmin',
        ])
            expect(inventoryProxyPath(path)).toBeNull()
        expect(inventoryProxyPath('barcode-lookup/ABC.123%20RED')).toBe('barcode-lookup/ABC.123%20RED')
    })
})

describe('sign-in failures', () => {
    it('distinguishes an API server failure from an unreachable API', async () => {
        const { inventoryLoginFailure } = await import('../../shared/inventoryLoginFailure')
        expect(inventoryLoginFailure(500).statusMessage).toContain('server error')
        expect(inventoryLoginFailure(503).statusMessage).toContain('server error')
        expect(inventoryLoginFailure().statusMessage).toBe('Cannot connect to the inventory service.')
        expect(inventoryLoginFailure(401)).toEqual({
            statusCode: 401,
            statusMessage: 'The access token was not accepted.',
        })
        expect(inventoryLoginFailure(404).statusMessage).toContain('API was not found')
        expect(inventoryLoginFailure(undefined, 'UNABLE_TO_VERIFY_LEAF_SIGNATURE').statusMessage).toContain(
            'certificate could not be verified',
        )
    })
})
