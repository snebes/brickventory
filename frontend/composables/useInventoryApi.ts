import type { InventoryRecord, PageResult } from '~/shared/inventory'

export function useInventoryApi() {
    const fetcher = useRequestFetch()
    const labels = useState<Record<string, { label: string; resource: string }>>('inventory-labels', () => ({}))
    const request = async <T = { data: InventoryRecord }>(path: string, options: Record<string, any> = {}) => {
        const result = await fetcher<T>(`/api/inventory/${path}`, { retry: 0, ...options })
        if (options.method && options.method !== 'GET') labels.value = {}
        return result
    }
    return {
        request,
        list: (resource: string, query: Record<string, any> = {}) => request<PageResult>(resource, { query }),
        get: (resource: string, id: number | string) => request(`${resource}/${id}`),
        create: (resource: string, body: Record<string, any>) => request(resource, { method: 'POST', body }),
        update: (resource: string, id: number, body: Record<string, any>) =>
            request(`${resource}/${id}`, { method: 'PATCH', body }),
        action: (resource: string, id: number, action: string, body: Record<string, any> = {}) =>
            request(`${resource}/${id}/${action}`, { method: 'POST', body }),
        remove: (resource: string, id: number) => request(`${resource}/${id}`, { method: 'DELETE' }),
    }
}
