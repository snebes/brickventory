export const useApi = () => {
  const fetchAPI = async (endpoint: string, options: any = {}) => {
    try {
      // Use relative path - Nitro will proxy /api/* requests to the backend
      const { data, error } = await useFetch(endpoint, {
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
    getInventoryAdjustments: (params?: { status?: string; type?: string }) => {
      const queryParams = new URLSearchParams()
      if (params?.status) queryParams.append('status', params.status)
      if (params?.type) queryParams.append('type', params.type)
      
      const query = queryParams.toString()
      return fetchAPI(`/api/inventory-adjustments${query ? '?' + query : ''}`)
    },
    getInventoryAdjustment: (id: number) => fetchAPI(`/api/inventory-adjustments/${id}`),
    createInventoryAdjustment: (adjustment: any) => fetchAPI('/api/inventory-adjustments', {
      method: 'POST',
      body: adjustment
    }),
    postInventoryAdjustment: (id: number) => fetchAPI(`/api/inventory-adjustments/${id}/post`, {
      method: 'POST'
    }),
    reverseInventoryAdjustment: (id: number, reason: string) => fetchAPI(`/api/inventory-adjustments/${id}/reverse`, {
      method: 'POST',
      body: { reason }
    }),
    approveInventoryAdjustment: (id: number, approverId: string) => fetchAPI(`/api/inventory-adjustments/${id}/approve`, {
      method: 'POST',
      body: { approverId }
    }),
    deleteInventoryAdjustment: (id: number) => fetchAPI(`/api/inventory-adjustments/${id}`, {
      method: 'DELETE'
    }),
    getPendingApprovalAdjustments: () => fetchAPI('/api/inventory-adjustments/pending-approval'),
    getInventoryAdjustmentReasons: () => fetchAPI('/api/inventory-adjustments/reasons'),
    
    // Physical Counts
    getPhysicalCounts: (params?: { status?: string; type?: string }) => {
      const queryParams = new URLSearchParams()
      if (params?.status) queryParams.append('status', params.status)
      if (params?.type) queryParams.append('type', params.type)
      
      const query = queryParams.toString()
      return fetchAPI(`/api/physical-counts${query ? '?' + query : ''}`)
    },
    getPhysicalCount: (id: number) => fetchAPI(`/api/physical-counts/${id}`),
    createPhysicalCount: (count: any) => fetchAPI('/api/physical-counts', {
      method: 'POST',
      body: count
    }),
    completePhysicalCount: (id: number) => fetchAPI(`/api/physical-counts/${id}/complete`, {
      method: 'POST'
    }),
    createAdjustmentFromCount: (id: number, autoPost: boolean = false) => fetchAPI(`/api/physical-counts/${id}/create-adjustment`, {
      method: 'POST',
      body: { autoPost }
    }),
    recordPhysicalCountLine: (countId: number, lineId: number, countedQuantity: number, countedBy: string) => fetchAPI(`/api/physical-counts/${countId}/lines/${lineId}/count`, {
      method: 'PUT',
      body: { countedQuantity, countedBy }
    }),
    recordPhysicalCountRecount: (countId: number, lineId: number, recountQuantity: number, verifiedBy: string) => fetchAPI(`/api/physical-counts/${countId}/lines/${lineId}/recount`, {
      method: 'POST',
      body: { recountQuantity, verifiedBy }
    }),
    getCycleCountsDue: (locationId?: number) => {
      const query = locationId ? `?locationId=${locationId}` : ''
      return fetchAPI(`/api/cycle-counts/due${query}`)
    },
    
    // Locations
    getLocations: (params?: { type?: string; active?: boolean }) => {
      const queryParams = new URLSearchParams()
      if (params?.type) queryParams.append('type', params.type)
      if (params?.active !== undefined) queryParams.append('active', params.active.toString())
      
      const query = queryParams.toString()
      return fetchAPI(`/api/locations${query ? '?' + query : ''}`)
    },
    getLocation: (id: number) => fetchAPI(`/api/locations/${id}`),
    createLocation: (location: any) => fetchAPI('/api/locations', {
      method: 'POST',
      body: location
    }),
    updateLocation: (id: number, location: any) => fetchAPI(`/api/locations/${id}`, {
      method: 'PUT',
      body: location
    }),
    activateLocation: (id: number) => fetchAPI(`/api/locations/${id}/activate`, {
      method: 'POST'
    }),
    deactivateLocation: (id: number) => fetchAPI(`/api/locations/${id}/deactivate`, {
      method: 'POST'
    }),
    getLocationInventory: (id: number, itemId?: number) => {
      const query = itemId ? `?itemId=${itemId}` : ''
      return fetchAPI(`/api/locations/${id}/inventory${query}`)
    },
    getLocationLowStock: (id: number, threshold?: number) => {
      const query = threshold ? `?threshold=${threshold}` : ''
      return fetchAPI(`/api/locations/${id}/low-stock${query}`)
    },
    getFulfillmentLocations: () => fetchAPI('/api/locations/fulfillment'),
    getReceivingLocations: () => fetchAPI('/api/locations/receiving'),
    
    // Inventory Balances
    getInventoryBalances: (params?: { itemId?: number; locationId?: number }) => {
      const queryParams = new URLSearchParams()
      if (params?.itemId) queryParams.append('itemId', params.itemId.toString())
      if (params?.locationId) queryParams.append('locationId', params.locationId.toString())
      
      const query = queryParams.toString()
      return fetchAPI(`/api/inventory-balances${query ? '?' + query : ''}`)
    },
    getInventoryBalancesByItem: (itemId: number) => fetchAPI(`/api/inventory-balances/by-item/${itemId}`),
    getInventoryBalancesByLocation: (locationId: number) => fetchAPI(`/api/inventory-balances/by-location/${locationId}`),
    getInventoryBalanceSummary: () => fetchAPI('/api/inventory-balances/summary'),
    checkInventoryAvailability: (itemId: number, locationId: number, quantity: number) => fetchAPI('/api/inventory-balances/check-availability', {
      method: 'POST',
      body: { itemId, locationId, quantity }
    }),
    
    // Reports
    getBackorderedItemsReport: () => fetchAPI('/api/reports/backordered-items/json'),
    downloadBackorderedItemsCsv: () => {
      const config = useRuntimeConfig()
      window.open(`${config.public.apiBase}/api/reports/backordered-items`, '_blank')
    }
  }
}
