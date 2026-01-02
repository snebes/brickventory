import { createRouter, createWebHashHistory } from 'vue-router';
import * as Vue from 'vue';
import { loadModule } from 'vue3-sfc-loader';

const options = {
    moduleCache: {
        vue: Vue,
    },
    async getFile(url) {
        const res = await fetch(url);
        if (!res.ok)
            throw Object.assign(new Error(res.statusText + ' ' + url), { res });
        return {
            getContentData: asBinary => asBinary ? res.arrayBuffer() : res.text(),
        }
    },
    addStyle(textContent) {
        const style = Object.assign(document.createElement('style'), { textContent });
        const ref = document.head.getElementsByTagName('style')[0] || null;
        document.head.insertBefore(style, ref);
    },
};

// Helper function to load .vue components
const loadComponent = (path) => {
    return () => loadModule(path, options);
};

const routes = [
    {
        path: '/',
        redirect: '/purchase-orders'
    },
    {
        path: '/purchase-orders',
        name: 'PurchaseOrdersList',
        component: loadComponent('./components/PurchaseOrdersList.vue')
    },
    {
        path: '/purchase-orders/new',
        name: 'PurchaseOrderCreate',
        component: loadComponent('./components/PurchaseOrderForm.vue')
    },
    {
        path: '/purchase-orders/:id/edit',
        name: 'PurchaseOrderEdit',
        component: loadComponent('./components/PurchaseOrderForm.vue')
    },
    {
        path: '/sales-orders',
        name: 'SalesOrdersList',
        component: loadComponent('./components/SalesOrdersList.vue')
    },
    {
        path: '/sales-orders/new',
        name: 'SalesOrderCreate',
        component: loadComponent('./components/SalesOrderForm.vue')
    },
    {
        path: '/sales-orders/:id/edit',
        name: 'SalesOrderEdit',
        component: loadComponent('./components/SalesOrderForm.vue')
    }
];

const router = createRouter({
    history: createWebHashHistory(),
    routes
});

export default router;
