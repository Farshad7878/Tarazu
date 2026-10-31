import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

// Layouts
import AdminLayout from '../components/Layout/AdminLayout.vue';
import ReceptionLayout from '../components/Layout/ReceptionLayout.vue';

// Auth
import Login from '../views/Auth/Login.vue';



// Reception Views
const ReceptionDashboard = () => import('../views/Reception/Dashboard.vue');
const NewOrder = () => import('../views/Reception/NewOrder.vue');
const ReceptionCustomers = () => import('../views/Reception/Customers.vue');
const ReceptionGiftCards = () => import('../views/Reception/GiftCards.vue');
const ReceptionShift = () => import('../views/Reception/Shift.vue');
const Orders = () => import('../views/Reception/Orders.vue');

// Admin Views
const AdminDashboard = () => import('../views/Admin/Dashboard.vue');
const Customers = () => import('../views/Admin/Customers.vue');
const Categories = () => import('../views/Admin/Categories.vue');
const Products = () => import('../views/Admin/Products.vue');
const Discounts = () => import('../views/Admin/Discounts.vue');
const AdminCashShifts = () => import('../views/Admin/CashShifts.vue');
const AdminSmsCampaigns = () => import('../views/Admin/SmsCampaigns.vue');
const Reports = () => import('../views/Admin/Reports.vue');
const AdminUsers = () => import('../views/Admin/Users.vue');
const AdminPurchaseOrders = () => import('../views/Admin/PurchaseOrders.vue');
const AdminBranches = () => import('../views/Admin/Branches.vue');
const AdminStockTransfers = () => import('../views/Admin/StockTransfers.vue');
const AdminAuditLogs = () => import('../views/Admin/AuditLogs.vue');
const AdminGiftCards = () => import('../views/Admin/GiftCards.vue');
const AdminBackups = () => import('../views/Admin/Backups.vue');
const AdminBarcodeLabels = () => import('../views/Admin/BarcodeLabels.vue');

const routes = [
    {
        path: '/login',
        name: 'login',
        component: Login,
        meta: { requiresAuth: false }
    },
    {
        path: '/admin',
        component: AdminLayout,
        meta: { requiresAuth: true, requiresAdmin: true },
        children: [
            {
                path: 'dashboard',
                name: 'admin.dashboard',
                component: AdminDashboard
            },
            {
                path: 'customers',
                name: 'admin.customers',
                component: Customers
            },
            {
                path: 'categories',
                name: 'admin.categories',
                component: Categories
            },
            {
                path: 'products',
                name: 'admin.products',
                component: Products
            },
            {
                path: 'discounts',
                name: 'admin.discounts',
                component: Discounts
            },
            {
                path: 'cash-shifts',
                name: 'admin.cash-shifts',
                component: AdminCashShifts
            },
            {
                path: 'sms-campaigns',
                name: 'admin.sms-campaigns',
                component: AdminSmsCampaigns
            },
            {
                path: 'reports',
                name: 'admin.reports',
                component: Reports
            },
            {
                path: 'users',
                name: 'admin.users',
                component: AdminUsers
            },
            {
                path: 'purchase-orders',
                name: 'admin.purchase-orders',
                component: AdminPurchaseOrders
            },
            {
                path: 'branches',
                name: 'admin.branches',
                component: AdminBranches
            },
            {
                path: 'stock-transfers',
                name: 'admin.stock-transfers',
                component: AdminStockTransfers
            },
            {
                path: 'gift-cards',
                name: 'admin.gift-cards',
                component: AdminGiftCards
            },
            {
                path: 'barcode-labels',
                name: 'admin.barcode-labels',
                component: AdminBarcodeLabels
            },
            {
                path: 'audit-logs',
                name: 'admin.audit-logs',
                component: AdminAuditLogs
            },
            {
                path: 'backups',
                name: 'admin.backups',
                component: AdminBackups
            },
        ]
    },
    {
        path: '/reception',
        component: ReceptionLayout,
        meta: { requiresAuth: true, requiresReception: true },
        children: [
            {
                path: 'dashboard',
                name: 'reception.dashboard',
                component: ReceptionDashboard
            },
            {
                path: 'new-order',
                name: 'reception.new-order',
                component: NewOrder
            },
            {
                path: 'orders',
                name: 'reception.orders',
                component: Orders
            },
            {
                path: 'shift',
                name: 'reception.shift',
                component: ReceptionShift
            },
            {
                path: 'customers',
                name: 'reception.customers',
                component: ReceptionCustomers
            },
            {
                path: 'gift-cards',
                name: 'reception.gift-cards',
                component: ReceptionGiftCards
            },
        ]
    },
    {
        path: '/',
        redirect: '/login'
    }
];

const router = createRouter({
    history: createWebHistory(),
    routes
});

router.beforeEach(async (to) => {
    const authStore = useAuthStore();

    // بعد از refresh، نقش کاربر باید قبل از تصمیم‌گیری مسیر بازیابی شود
    if (authStore.isAuthenticated && !authStore.user) {
        await authStore.checkAuth();
    }

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return '/login';
    }

    if (to.meta.requiresAdmin && !authStore.isAdmin) {
        return authStore.isReception ? '/reception/dashboard' : '/login';
    }

    if (to.meta.requiresReception && !authStore.isReception) {
        return authStore.isAdmin ? '/admin/dashboard' : '/login';
    }

    return true;
});

export default router;