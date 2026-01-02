import { createRouter, createWebHashHistory } from 'vue-router';
import PurchaseOrdersList from './components/PurchaseOrdersList.js';
import PurchaseOrderForm from './components/PurchaseOrderForm.js';
import SalesOrdersList from './components/SalesOrdersList.js';
import SalesOrderForm from './components/SalesOrderForm.js';

const routes = [
    {
        path: '/',
        redirect: '/purchase-orders'
    },
    {
        path: '/purchase-orders',
        name: 'PurchaseOrdersList',
        component: PurchaseOrdersList
    },
    {
        path: '/purchase-orders/new',
        name: 'PurchaseOrderCreate',
        component: PurchaseOrderForm
    },
    {
        path: '/purchase-orders/:id/edit',
        name: 'PurchaseOrderEdit',
        component: PurchaseOrderForm
    },
    {
        path: '/sales-orders',
        name: 'SalesOrdersList',
        component: SalesOrdersList
    },
    {
        path: '/sales-orders/new',
        name: 'SalesOrderCreate',
        component: SalesOrderForm
    },
    {
        path: '/sales-orders/:id/edit',
        name: 'SalesOrderEdit',
        component: SalesOrderForm
    }
];

const router = createRouter({
    history: createWebHashHistory(),
    routes
});

export default router;
