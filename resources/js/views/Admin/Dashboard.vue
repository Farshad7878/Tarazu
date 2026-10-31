<template>
    <div class="admin-dashboard">
        <div class="dashboard-header">
            <h2>داشبورد مدیریت</h2>
            <p>آمار و اطلاعات کلی سیستم</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><Users :size="22" :stroke-width="1.9" /></div>
                <div class="stat-info">
                    <div class="stat-value">{{ stats.total_customers || 0 }}</div>
                    <div class="stat-label">کل مشتریان</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><Package :size="22" :stroke-width="1.9" /></div>
                <div class="stat-info">
                    <div class="stat-value">{{ stats.total_orders || 0 }}</div>
                    <div class="stat-label">کل سفارشات</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><UserRound :size="22" :stroke-width="1.9" /></div>
                <div class="stat-info">
                    <div class="stat-value">{{ stats.total_receptionists || 0 }}</div>
                    <div class="stat-label">ریسپشن‌ها</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><Clock :size="22" :stroke-width="1.9" /></div>
                <div class="stat-info">
                    <div class="stat-value">{{ stats.today_orders || 0 }}</div>
                    <div class="stat-label">سفارشات امروز</div>
                </div>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="recent-activity">
                <h3>فعالیت‌های اخیر</h3>
                <div class="activity-list">
                    <div 
                        v-for="activity in recentActivity" 
                        :key="activity.id"
                        class="activity-item"
                    >
                        <div class="activity-icon"><Bell :size="22" :stroke-width="1.9" /></div>
                        <div class="activity-content">
                            <div class="activity-message">{{ activity.message }}</div>
                            <div class="activity-time">{{ activity.time }}</div>
                        </div>
                    </div>
                    <div v-if="recentActivity.length === 0" class="no-activity">
                        هیچ فعالیتی یافت نشد
                    </div>
                </div>
            </div>

            <div class="quick-actions">
                <h3>دسترسی سریع</h3>
                <div class="actions-grid">
                    <router-link to="/admin/customers" class="action-card">
                        <div class="action-icon"><Users :size="22" :stroke-width="1.9" /></div>
                        <div class="action-title">مدیریت مشتریان</div>
                    </router-link>
                    
                    <router-link to="/admin/products" class="action-card">
                        <div class="action-icon"><Package :size="22" :stroke-width="1.9" /></div>
                        <div class="action-title">مدیریت محصولات</div>
                    </router-link>
                    
                    <router-link to="/admin/discounts" class="action-card">
                        <div class="action-icon"><Ticket :size="22" :stroke-width="1.9" /></div>
                        <div class="action-title">تخفیف‌ها</div>
                    </router-link>
                    
                    <router-link to="/admin/users" class="action-card">
                        <div class="action-icon"><UserCog :size="22" :stroke-width="1.9" /></div>
                        <div class="action-title">کاربران سیستم</div>
                    </router-link>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { Users, Package, UserRound, Clock, Bell, Ticket, UserCog } from 'lucide-vue-next';

export default {
    components: { Users, Package, UserRound, Clock, Bell, Ticket, UserCog },
    name: 'AdminDashboard',
    setup() {
        const stats = ref({});
        const recentActivity = ref([]);
        const loading = ref(true);

        const fetchDashboardData = async () => {
            try {
                const response = await axios.get('/api/admin/dashboard');
                stats.value = response.data.stats;
                recentActivity.value = response.data.recent_activity;
            } catch (error) {
                console.error('Error fetching dashboard data:', error);
            } finally {
                loading.value = false;
            }
        };

        onMounted(() => {
            fetchDashboardData();
        });

        return {
            stats,
            recentActivity,
            loading
        };
    }
}
</script>

<style scoped>
.admin-dashboard {
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

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(250px, 100%), 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 16px;
    border: 1px solid #e2e8f0;
}

.stat-icon {
    font-size: 2rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: #2d3748;
    line-height: 1;
}

.stat-label {
    color: #718096;
    font-size: 0.875rem;
    margin-top: 4px;
}

.dashboard-content {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, min(400px, 100%));
    gap: 24px;
}

@media (max-width: 900px) {
    .dashboard-content {
        grid-template-columns: minmax(0, 1fr);
    }
}

.recent-activity, .quick-actions {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
}

.recent-activity h3, .quick-actions h3 {
    color: #2d3748;
    margin-bottom: 16px;
    font-size: 1.25rem;
}

.activity-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px;
    border-radius: 8px;
    background: #f7fafc;
}

.activity-icon {
    font-size: 1.25rem;
}

.activity-message {
    color: #4a5568;
    font-weight: 500;
}

.activity-time {
    color: #718096;
    font-size: 0.875rem;
    margin-top: 4px;
}

.no-activity {
    text-align: center;
    color: #a0aec0;
    padding: 20px;
}

.actions-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.action-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: #f7fafc;
    border-radius: 8px;
    text-decoration: none;
    color: inherit;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.action-card:hover {
    background: #edf2f7;
    border-color: #3182ce;
    transform: translateY(-2px);
}

.action-icon {
    font-size: 2rem;
    margin-bottom: 8px;
}

.action-title {
    color: #4a5568;
    font-weight: 500;
    text-align: center;
}
</style>