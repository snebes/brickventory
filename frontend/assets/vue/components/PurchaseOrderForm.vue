<script setup>
import { ref, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';

const router = useRouter();
const route = useRoute();

const form = ref({
    orderNumber: '',
    orderDate: new Date().toISOString().split('T')[0],
    status: 'pending',
    reference: '',
    notes: '',
    lines: []
});

const items = ref([]);
const loading = ref(false);
const orderId = ref(null);

const loadItems = async () => {
    try {
        const response = await fetch('/api/items');
        items.value = await response.json();
    } catch (error) {
        console.error('Error loading items:', error);
    }
};

const loadOrder = async () => {
    try {
        const response = await fetch(`/api/purchase-orders/${orderId.value}`);
        const order = await response.json();
        form.value = {
            orderNumber: order.orderNumber,
            orderDate: order.orderDate.split(' ')[0],
            status: order.status,
            reference: order.reference || '',
            notes: order.notes || '',
            lines: order.lines.map(line => ({
                itemId: line.item?.id || '',
                quantityOrdered: line.quantityOrdered,
                rate: line.rate
            }))
        };
    } catch (error) {
        console.error('Error loading purchase order:', error);
    }
};

const addLine = () => {
    form.value.lines.push({
        itemId: '',
        quantityOrdered: 1,
        rate: 0
    });
};

const removeLine = (index) => {
    form.value.lines.splice(index, 1);
};

const save = async () => {
    loading.value = true;
    try {
        const url = orderId.value 
            ? `/api/purchase-orders/${orderId.value}`
            : '/api/purchase-orders';
        const method = orderId.value ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(form.value)
        });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Failed to save purchase order');
        }
        
        router.push('/purchase-orders');
    } catch (error) {
        console.error('Error saving purchase order:', error);
        alert('Error: ' + error.message);
    } finally {
        loading.value = false;
    }
};

const cancel = () => {
    router.push('/purchase-orders');
};

onMounted(async () => {
    orderId.value = route.params.id;
    await loadItems();
    if (orderId.value) {
        await loadOrder();
    }
});
</script>

<template>
    <div>
        <div class="header">
            <h2>{{ orderId ? 'Edit' : 'Create' }} Purchase Order</h2>
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
                <label>Reference</label>
                <input v-model="form.reference" placeholder="Vendor reference, PO number, etc.">
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
                                {{ item.itemId }} - {{ item.itemName }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label>Quantity</label>
                        <input v-model.number="line.quantityOrdered" type="number" min="1">
                    </div>
                    <div>
                        <label>Rate</label>
                        <input v-model.number="line.rate" type="number" step="0.01" min="0">
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
