export const useApi = () => {
  const config = useRuntimeConfig()
  
  const fetchAPI = async (endpoint: string, options: any = {}) => {
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
    getItems: () => fetchAPI('/api/items')
  }
}
