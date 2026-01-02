<script>
export default {
    name: 'SalesOrderForm',
    data() {
        return {
            form: {
                orderNumber: '',
                orderDate: new Date().toISOString().split('T')[0],
                status: 'pending',
                notes: '',
                lines: []
            },
            items: [],
            loading: false,
            orderId: null
        };
    },
    async mounted() {
        this.orderId = this.$route.params.id;
        await this.loadItems();
        if (this.orderId) {
            await this.loadOrder();
        }
    },
    methods: {
        async loadItems() {
            try {
                const response = await fetch('/api/items');
                this.items = await response.json();
            } catch (error) {
                console.error('Error loading items:', error);
            }
        },
        async loadOrder() {
            try {
                const response = await fetch(`/api/sales-orders/${this.orderId}`);
                const order = await response.json();
                this.form = {
                    orderNumber: order.orderNumber,
                    orderDate: order.orderDate.split(' ')[0],
                    status: order.status,
                    notes: order.notes || '',
                    lines: order.lines.map(line => ({
                        itemId: line.item.id,
                        quantityOrdered: line.quantityOrdered
                    }))
                };
            } catch (error) {
                console.error('Error loading sales order:', error);
            }
        },
        addLine() {
            this.form.lines.push({
                itemId: '',
                quantityOrdered: 1
            });
        },
        removeLine(index) {
            this.form.lines.splice(index, 1);
        },
        async save() {
            this.loading = true;
            try {
                const url = this.orderId 
                    ? `/api/sales-orders/${this.orderId}`
                    : '/api/sales-orders';
                const method = this.orderId ? 'PUT' : 'POST';
                
                const response = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(this.form)
                });
                
                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.error || 'Failed to save sales order');
                }
                
                this.$router.push('/sales-orders');
            } catch (error) {
                console.error('Error saving sales order:', error);
                alert('Error: ' + error.message);
            } finally {
                this.loading = false;
            }
        },
        cancel() {
            this.$router.push('/sales-orders');
        }
    }
};
</script>

<template>
    <div>
        <div class="header">
            <h2>{{ orderId ? 'Edit' : 'Create' }} Sales Order</h2>
        </div>
        
        <div class="card">
            <div class="form-group">
                <label>Order Number</label>
                <input v-model="form.orderNumber" placeholder="Leave empty to auto-generate" :readonly="!!orderId">
            </div>
            
            <div class="form-group">
                <label>Order Date</label>
                <input v-model="form.orderDate" type="date">
            </div>
            
            <div class="form-group">
                <label>Status</label>
                <select v-model="form.status">
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Notes</label>
                <textarea v-model="form.notes" placeholder="Additional notes"></textarea>
            </div>
            
            <div class="line-items">
                <h3>Line Items</h3>
                <button class="btn btn-secondary" @click="addLine" style="margin: 10px 0;">Add Line</button>
                
                <div v-for="(line, index) in form.lines" :key="index" class="line-item">
                    <div>
                        <label>Item</label>
                        <select v-model="line.itemId">
                            <option value="">Select item</option>
                            <option v-for="item in items" :key="item.id" :value="item.id">
                                {{ item.itemId }} - {{ item.itemName }} (Available: {{ item.quantityAvailable }})
                            </option>
                        </select>
                    </div>
                    <div>
                        <label>Quantity</label>
                        <input v-model.number="line.quantityOrdered" type="number" min="1">
                    </div>
                    <div style="flex: 0;">
                        <button class="btn btn-danger" @click="removeLine(index)">Remove</button>
                    </div>
                </div>
            </div>
            
            <div class="actions">
                <button class="btn btn-success" @click="save" :disabled="loading">
                    {{ loading ? 'Saving...' : 'Save' }}
                </button>
                <button class="btn btn-secondary" @click="cancel" :disabled="loading">Cancel</button>
            </div>
        </div>
    </div>
</template>
