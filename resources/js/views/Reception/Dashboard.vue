<template>
    <div class="reception-dashboard">
        <div class="dashboard-header">
            <h2>صفحه اصلی ریسپشن</h2>
            <p>سیستم مدیریت فروش و مشتریان</p>
        </div>

        <div class="quick-actions-grid">
            <router-link to="/reception/new-order" class="quick-action primary">
                <div class="action-icon"><Plus :size="22" :stroke-width="1.9" /></div>
                <div class="action-title">ثبت سفارش جدید</div>
                <div class="action-description">ثبت خرید جدید برای مشتری</div>
            </router-link>
            
            <!-- <router-link to="/reception/customers" class="quick-action">
                <div class="action-icon">👥</div>
                <div class="action-title">مدیریت مشتریان</div>
                <div class="action-description">ثبت و جستجوی مشتریان</div>
            </router-link>-->
            
            <router-link to="/reception/orders" class="quick-action">
                <div class="action-icon"><ShoppingBag :size="22" :stroke-width="1.9" /></div>
                <div class="action-title">سفارشات من</div>
                <div class="action-description">مشاهده تاریخچه سفارشات</div>
            </router-link> 
        </div>

        <div class="stats-section">
            <h3>آمار امروز</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value">{{ todayStats.orders || 0 }}</div>
                    <div class="stat-label">سفارشات امروز</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">{{ todayStats.customers || 0 }}</div>
                    <div class="stat-label">مشتریان جدید</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">{{ formatPrice(todayStats.revenue || 0) }}</div>
                    <div class="stat-label">درآمد امروز</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { Plus, ShoppingBag } from 'lucide-vue-next';

export default {
    components: { Plus, ShoppingBag },
    name: 'ReceptionDashboard',
    setup() {
        const todayStats = ref({});

        const fetchTodayStats = async () => {
            try {
                // این endpoint بعداً ایجاد می‌شود
                const response = await axios.get('/api/reception/today-stats');
                todayStats.value = response.data;
                
                // داده‌های نمونه
                // todayStats.value = {
                //     orders: 12,
                //     customers: 3,
                //     revenue: 450000
                // };
            } catch (error) {
                console.error('Error fetching today stats:', error);
            }
        };

        const formatPrice = (price) => {
            return new Intl.NumberFormat('fa-IR').format(price) + ' تومان';
        };

        onMounted(() => {
            fetchTodayStats();
        });

        return {
            todayStats,
            formatPrice
        };
    }
}
</script>

<style scoped>
.reception-dashboard {
    max-width: 1200px;
    margin: 0 auto;
}

.dashboard-header {
    margin-bottom: 32px;
}

.dashboard-header h2 {
    color: #2d3748;
    font-size: 1.875rem;
    margin-bottom: 8px;
}

.dashboard-header p {
    color: #718096;
    font-size: 1.125rem;
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(300px, 100%), 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.quick-action {
    background: white;
    border-radius: 12px;
    padding: 24px;
    text-decoration: none;
    color: inherit;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 2px solid transparent;
    transition: all 0.3s ease;
}

.quick-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.quick-action.primary {
    border-color: #3182ce;
    background: linear-gradient(135deg, #3182ce 0%, #2c5aa0 100%);
    color: white;
}

.quick-action.primary .action-title {
    color: white;
}

.quick-action.primary .action-description {
    color: #ebf8ff;
}

.action-icon {
    font-size: 2.5rem;
    margin-bottom: 12px;
}

.action-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 8px;
}

.action-description {
    color: #718096;
    font-size: 0.875rem;
}

.stats-section {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.stats-section h3 {
    color: #2d3748;
    margin-bottom: 20px;
    font-size: 1.25rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(200px, 100%), 1fr));
    gap: 20px;
}

.stat-card {
    text-align: center;
    padding: 20px;
    background: #f7fafc;
    border-radius: 8px;
}

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: #3182ce;
    margin-bottom: 8px;
}

.stat-label {
    color: #718096;
    font-size: 0.875rem;
}
</style>