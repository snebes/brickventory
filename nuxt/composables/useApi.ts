export const useApi = () => {
  const config = useRuntimeConfig()
  
  const fetchAPI = async (endpoint: string, options: any = {}) => {
    try {
      const { data, error } = await useFetch(`${config.public.apiBase}${endpoint}`, {
        ...options,
        headers: {
          'Content-Type': 'application/json',
          ...options.headers
        }
      })
      
      if (error.value) {
        console.error('API Error:', error.value)
        throw error.value
      }
      
      return data.value
    } catch (err) {
      console.error('API Error:', err)
      throw err
    }
  }
  
  return {
    // Purchase Orders
    getPurchaseOrders: () => fetchAPI('/api/purchase-orders'),
    getPurchaseOrder: (id: number) => fetchAPI(`/api/purchase-orders/${id}`),
    createPurchaseOrder: (order: any) => fetchAPI('/api/purchase-orders', {
      method: 'POST',
      body: order
    }),
    updatePurchaseOrder: (id: number, order: any) => fetchAPI(`/api/purchase-orders/${id}`, {
      method: 'PUT',
      body: order
    }),
    deletePurchaseOrder: (id: number) => fetchAPI(`/api/purchase-orders/${id}`, {
      method: 'DELETE'
    }),
    
    // Sales Orders
    getSalesOrders: () => fetchAPI('/api/sales-orders'),
    getSalesOrder: (id: number) => fetchAPI(`/api/sales-orders/${id}`),
    createSalesOrder: (order: any) => fetchAPI('/api/sales-orders', {
      method: 'POST',
      body: order
    }),
    updateSalesOrder: (id: number, order: any) => fetchAPI(`/api/sales-orders/${id}`, {
      method: 'PUT',
      body: order
    }),
    deleteSalesOrder: (id: number) => fetchAPI(`/api/sales-orders/${id}`, {
      method: 'DELETE'
    }),
    
    // Items
    getItems: (params?: { page?: number; limit?: number; search?: string }) => {
      const queryParams = new URLSearchParams()
      if (params?.page) queryParams.append('page', params.page.toString())
      if (params?.limit) queryParams.append('limit', params.limit.toString())
      if (params?.search) queryParams.append('search', params.search)
      
      const query = queryParams.toString()
      return fetchAPI(`/api/items${query ? '?' + query : ''}`)
    },
    getItem: (id: number) => fetchAPI(`/api/items/${id}`),
    createItem: (item: any) => fetchAPI('/api/items', {
      method: 'POST',
      body: item
    }),
    updateItem: (id: number, item: any) => fetchAPI(`/api/items/${id}`, {
      method: 'PUT',
      body: item
    }),
    deleteItem: (id: number) => fetchAPI(`/api/items/${id}`, {
      method: 'DELETE'
    })
  }
}
