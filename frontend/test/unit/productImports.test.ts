import { describe, expect, it } from 'vitest'
import { inventoryProxyPath } from '../../shared/inventoryProxy'
import { importIsActive, type ProductImport } from '../../shared/productImports'
describe('background import polling', () => {
    it('allows import status routes and rejects storage or traversal paths', () => {
        const id = '019b0000-0000-7000-8000-000000000001'
        for (const route of [
            'product-imports',
            `product-imports/${id}`,
            `product-imports/${id}/resume`,
            `product-imports/${id}/errors`,
        ])
            expect(inventoryProxyPath(route)).toBe(route)
        for (const route of ['product-imports/../.env', 'product-imports/file.csv', `product-imports/${id}/source`])
            expect(inventoryProxyPath(route)).toBeNull()
    })
    it('polls only jobs which can still make progress', () => {
        for (const status of ['queued', 'running', 'retrying'])
            expect(importIsActive({ status } as ProductImport)).toBe(true)
        for (const status of ['completed', 'failed', 'cancelled'])
            expect(importIsActive({ status } as ProductImport)).toBe(false)
    })
})
