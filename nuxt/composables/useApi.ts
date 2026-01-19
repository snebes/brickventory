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
    // Dashboard
    getDashboardMetrics: () => fetchAPI('/api/dashboard'),
    
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
    
    // Item Receipts
    getItemReceipts: () => fetchAPI('/api/item-receipts'),
    getItemReceipt: (id: number) => fetchAPI(`/api/item-receipts/${id}`),
    createItemReceipt: (receipt: any) => fetchAPI('/api/item-receipts', {
      method: 'POST',
      body: receipt
    }),
    deleteItemReceipt: (id: number) => fetchAPI(`/api/item-receipts/${id}`, {
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
    }),
    
    // Inventory Adjustments
    getInventoryAdjustments: () => fetchAPI('/api/inventory-adjustments'),
    getInventoryAdjustment: (id: number) => fetchAPI(`/api/inventory-adjustments/${id}`),
    createInventoryAdjustment: (adjustment: any) => fetchAPI('/api/inventory-adjustments', {
      method: 'POST',
      body: adjustment
    }),
    deleteInventoryAdjustment: (id: number) => fetchAPI(`/api/inventory-adjustments/${id}`, {
      method: 'DELETE'
    }),
    getInventoryAdjustmentReasons: () => fetchAPI('/api/inventory-adjustments/reasons'),
    
    // Reports
    getBackorderedItemsReport: () => fetchAPI('/api/reports/backordered-items/json'),
    downloadBackorderedItemsCsv: () => {
      const config = useRuntimeConfig()
      window.open(`${config.public.apiBase}/api/reports/backordered-items`, '_blank')
    }
  }
}
