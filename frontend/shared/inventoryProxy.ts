import { inventoryResources } from './inventory'

export function inventoryProxyPath(raw: string): string | null {
    const segments = raw.split('/')
    if (segments.some((segment) => !segment)) return null
    if (segments[0] === 'barcode-lookup' && segments.length === 2) {
        let barcode: string
        try {
            barcode = decodeURIComponent(segments[1]!)
        } catch {
            return null
        }
        if (
            !barcode ||
            barcode.length > 255 ||
            barcode === '.' ||
            barcode === '..' ||
            /[\/\\\u0000-\u001f]/.test(barcode)
        )
            return null
        return `barcode-lookup/${encodeURIComponent(barcode)}`
    }
    if (raw === 'dashboard') return raw
    if (/^product-imports(?:\/[a-f0-9-]{36}(?:\/(?:cancel|resume|errors))?)?$/i.test(raw)) return raw
    if (!/^[a-z0-9/-]+$/i.test(raw) || !inventoryResources[segments[0]!]) return null
    return raw
}
