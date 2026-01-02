<script>
export default {
    name: 'PurchaseOrdersList',
    data() {
        return {
            orders: [],
            loading: true
        };
    },
    async mounted() {
        await this.loadOrders();
    },
    methods: {
        async loadOrders() {
            this.loading = true;
            try {
                const response = await fetch('/api/purchase-orders');
                this.orders = await response.json();
            } catch (error) {
                console.error('Error loading purchase orders:', error);
            } finally {
                this.loading = false;
            }
        },
        createOrder() {
            this.$router.push('/purchase-orders/new');
        },
        editOrder(order) {
            this.$router.push(`/purchase-orders/${order.id}/edit`);
        },
        async deleteOrder(id) {
            if (!confirm('Are you sure you want to delete this purchase order?')) {
                return;
            }
            try {
                await fetch(`/api/purchase-orders/${id}`, {
                    method: 'DELETE'
                });
                await this.loadOrders();
            } catch (error) {
                console.error('Error deleting purchase order:', error);
                alert('Error deleting purchase order');
            }
        }
    }
};
</script>

<template>
    <div>
        <div class="header">
            <h2>Purchase Orders</h2>
            <button class="btn btn-primary" @click="createOrder">Create Purchase Order</button>
        </div>
        
        <div v-if="loading" class="loading">Loading...</div>
        
        <div v-else-if="orders.length === 0" class="card empty-state">
            <h3>No purchase orders yet</h3>
            <p>Create your first purchase order to get started</p>
        </div>
        
        <div v-else class="card">
            <table>
                <thead>
                    <tr>
                        <th>Order Number</th>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Status</th>
                        <th>Lines</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="order in orders" :key="order.id">
                        <td>{{ order.orderNumber }}</td>
                        <td>{{ new Date(order.orderDate).toLocaleDateString() }}</td>
                        <td>{{ order.reference || '-' }}</td>
                        <td>
                            <span class="badge" :class="'badge-' + order.status">
                                {{ order.status }}
                            </span>
                        </td>
                        <td>{{ order.lines.length }}</td>
                        <td>
                            <button class="btn btn-secondary" style="margin-right: 5px;" @click="editOrder(order)">Edit</button>
                            <button class="btn btn-danger" @click="deleteOrder(order.id)">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
