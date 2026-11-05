import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

import AppLayout from '../layouts/AppLayout.vue';
import LoginView from '../views/auth/LoginView.vue';
import DashboardView from '../views/dashboard/DashboardView.vue';
import PosTerminalView from '../views/pos/PosTerminalView.vue';
import DispensingView from '../views/dispensing/DispensingView.vue';
import InventoryView from '../views/inventory/InventoryView.vue';
import PurchasesView from '../views/procurement/PurchasesView.vue';
import ExpensesView from '../views/finance/ExpensesView.vue';
import AccountingView from '../views/finance/AccountingView.vue';
import ReportsView from '../views/reports/ReportsView.vue';
import SettingsView from '../views/settings/SettingsView.vue';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: LoginView,
        meta: { guestOnly: true },
    },
    {
        path: '/',
        component: AppLayout,
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                redirect: '/dashboard',
            },
            {
                path: 'dashboard',
                name: 'dashboard',
                component: DashboardView,
            },
            {
                path: 'pos',
                name: 'pos',
                component: PosTerminalView,
                meta: { permission: 'pos.access' },
            },
            {
                path: 'dispensing',
                name: 'dispensing',
                component: DispensingView,
                meta: { permission: 'dispensing.view' },
            },
            {
                path: 'inventory',
                name: 'inventory',
                component: InventoryView,
                meta: { permission: 'inventory.view' },
            },
            {
                path: 'purchases',
                name: 'purchases',
                component: PurchasesView,
                meta: { permission: 'procurement.view_orders' },
            },
            {
                path: 'expenses',
                name: 'expenses',
                component: ExpensesView,
                meta: { permission: 'finance.view_expenses' },
            },
            {
                path: 'accounting',
                name: 'accounting',
                component: AccountingView,
                meta: { permission: 'finance.chart_of_accounts' },
            },
            {
                path: 'reports',
                name: 'reports',
                component: ReportsView,
                meta: { permission: 'reports.sales' },
            },
            {
                path: 'settings',
                name: 'settings',
                component: SettingsView,
                meta: { permission: 'settings.manage' },
            },
        ],
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/dashboard',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to, from) => {
    const authStore = useAuthStore();

    if (authStore.token && !authStore.user) {
        await authStore.fetchProfile();
    }

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return { name: 'login' };
    }

    if (to.meta.guestOnly && authStore.isAuthenticated) {
        return { name: 'dashboard' };
    }

    if (to.meta.permission && !authStore.hasPermission(to.meta.permission)) {
        return { name: 'dashboard' };
    }
});

export default router;
