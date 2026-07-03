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
    // Use absolute path from the assets directory to ensure correct resolution
    return () => loadModule(`/assets/vue/components/${path}`, options);
};

const routes = [
    {
        path: '/',
        redirect: '/purchase-orders'
    },
    {
        path: '/purchase-orders',
        name: 'PurchaseOrdersList',
        component: loadComponent('PurchaseOrdersList.vue')
    },
    {
        path: '/purchase-orders/new',
        name: 'PurchaseOrderCreate',
        component: loadComponent('PurchaseOrderForm.vue')
    },
    {
        path: '/purchase-orders/:id/edit',
        name: 'PurchaseOrderEdit',
        component: loadComponent('PurchaseOrderForm.vue')
    },
    {
        path: '/sales-orders',
        name: 'SalesOrdersList',
        component: loadComponent('SalesOrdersList.vue')
    },
    {
        path: '/sales-orders/new',
        name: 'SalesOrderCreate',
        component: loadComponent('SalesOrderForm.vue')
    },
    {
        path: '/sales-orders/:id/edit',
        name: 'SalesOrderEdit',
        component: loadComponent('SalesOrderForm.vue')
    }
];

const router = createRouter({
    history: createWebHashHistory(),
    routes
});

export default router;
