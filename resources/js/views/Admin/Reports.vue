<template>
    <div class="reports-page">
        <div class="page-header">
            <h2>📈 گزارشات و آمار</h2>
            <div class="header-actions">
                <button @click="exportReport" class="btn-secondary">
                    📊 خروجی (CSV برای Excel)
                </button>
                <button @click="refreshData" class="btn-primary">
                    🔄 بروزرسانی
                </button>
            </div>
        </div>

        <!-- فیلترهای تاریخ -->
        <div class="filters-section">
            <div class="filter-row">
                <div class="filter-group">
                    <label>بازه زمانی:</label>
                    <select v-model="filters.period" @change="handlePeriodChange">
                        <option value="today">امروز</option>
                        <option value="yesterday">دیروز</option>
                        <option value="week">هفته جاری</option>
                        <option value="month">ماه جاری</option>
                        <option value="last_month">ماه گذشته</option>
                        <option value="year">سال جاری</option>
                        <option value="custom">سفارشی</option>
                    </select>
                </div>

                <div v-if="filters.period === 'custom'" class="filter-group">
                    <label>از تاریخ:</label>
                    <input type="date" v-model="filters.start_date">
                </div>

                <div v-if="filters.period === 'custom'" class="filter-group">
                    <label>تا تاریخ:</label>
                    <input type="date" v-model="filters.end_date">
                </div>

                <div class="filter-group">
                    <label>ریسپشن:</label>
                    <select v-model="filters.reception_id">
                        <option value="">همه</option>
                        <option 
                            v-for="reception in receptions" 
                            :key="reception.id" 
                            :value="reception.id"
                        >
                            {{ reception.name }}
                        </option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>شعبه:</label>
                    <select v-model="filters.branch_id">
                        <option value="">همه‌ی شعبه‌ها</option>
                        <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                            {{ branch.name }}
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- کارت‌های آماری -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon">💰</div>
                <div class="stat-info">
                    <div class="stat-value">{{ formatPrice(stats.total_revenue) }}</div>
                    <div class="stat-label">کل درآمد</div>
                    <div class="stat-change" :class="getChangeClass(stats.revenue_change)">
                        {{ formatChange(stats.revenue_change) }}
                    </div>
                </div>
            </div>

            <div class="stat-card success">
                <div class="stat-icon">📦</div>
                <div class="stat-info">
                    <div class="stat-value">{{ stats.total_orders }}</div>
                    <div class="stat-label">تعداد سفارشات</div>
                    <div class="stat-change" :class="getChangeClass(stats.orders_change)">
                        {{ formatChange(stats.orders_change) }}
                    </div>
                </div>
            </div>

            <div class="stat-card info">
                <div class="stat-icon">👥</div>
                <div class="stat-info">
                    <div class="stat-value">{{ stats.total_customers }}</div>
                    <div class="stat-label">مشتریان جدید</div>
                    <div class="stat-change" :class="getChangeClass(stats.customers_change)">
                        {{ formatChange(stats.customers_change) }}
                    </div>
                </div>
            </div>

            <div v-if="stats.total_tax > 0" class="stat-card">
                <div class="stat-icon">🧾</div>
                <div class="stat-info">
                    <div class="stat-value">{{ formatPrice(stats.total_tax) }}</div>
                    <div class="stat-label">مالیات (ارزش افزوده)</div>
                </div>
            </div>

            <div class="stat-card warning">
                <div class="stat-icon">🎫</div>
                <div class="stat-info">
                    <div class="stat-value">{{ formatPrice(stats.total_discounts) }}</div>
                    <div class="stat-label">تخفیف اعمال شده</div>
                    <div class="stat-change" :class="getChangeClass(stats.discounts_change)">
                        {{ formatChange(stats.discounts_change) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- نمودارها و جداول -->
        <div class="charts-section">
            <div class="chart-row">
                <div class="chart-card">
                    <h3>📊 درآمد روزانه</h3>
                    <div class="chart-container">
                        <canvas ref="revenueChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <h3>🧾 فروش بر اساس دسته‌بندی</h3>
                    <div class="chart-container">
                        <canvas ref="productsChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="chart-row">
                <div class="chart-card">
                    <h3>👨‍💼 عملکرد ریسپشن‌ها</h3>
                    <div class="chart-container">
                        <canvas ref="receptionChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <h3>🕒 ساعات پرترافیک</h3>
                    <div class="chart-container">
                        <canvas ref="hoursChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- جدول سفارشات اخیر -->
        <div class="recent-orders">
            <h3>🆕 آخرین سفارشات</h3>
            <div class="table-container">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>شماره سفارش</th>
                            <th>مشتری</th>
                            <th>ریسپشن</th>
                            <th>مبلغ</th>
                            <th>تخفیف</th>
                            <th>تاریخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="order in recentOrders" :key="order.id">
                            <td>
                                <span class="order-number">#{{ order.order_number }}</span>
                            </td>
                            <td>{{ order.customer_name }}</td>
                            <td>{{ order.reception_name }}</td>
                            <td class="price">{{ formatPrice(order.total_amount) }}</td>
                            <td class="discount">{{ formatPrice(order.discount_amount) }}</td>
                            <td>{{ order.created_at }}</td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="recentOrders.length === 0" class="empty-state">
                    📝 هیچ سفارشی یافت نشد
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, nextTick, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { useNotificationStore } from '../../stores/notifications';
import Chart from 'chart.js/auto';

import { formatPrice } from '../../utils/format';
const CHART_COLORS = ['#38a169', '#3182ce', '#dd6b20', '#805ad5', '#d53f8c', '#319795', '#718096'];

export default {
    name: 'Reports',
    setup() {
        const stats = ref({});
        const recentOrders = ref([]);
        const categorySales = ref([]);
        const dailyRevenue = ref([]);
        const receptionPerformance = ref([]);
        const hourlyOrders = ref([]);
        const receptions = ref([]);
        const loading = ref(false);

        const revenueChart = ref(null);
        const productsChart = ref(null);
        const receptionChart = ref(null);
        const hoursChart = ref(null);

        const filters = ref({
            period: 'month',
            start_date: '',
            end_date: '',
            reception_id: '',
            branch_id: ''
        });

        const branches = ref([]);

        const notificationStore = useNotificationStore();
        let charts = [];

        const fetchReportsData = async () => {
            loading.value = true;
            try {
                const params = {
                    period: filters.value.period,
                    ...filters.value
                };

                const [
                    statsResponse,
                    ordersResponse,
                    receptionsResponse,
                    categorySalesResponse,
                    dailyResponse,
                    receptionPerformanceResponse,
                    hourlyResponse
                ] = await Promise.all([
                    axios.get('/api/admin/reports/stats', { params }),
                    axios.get('/api/admin/reports/recent-orders', { params }),
                    axios.get('/api/admin/users', { params: { role: 'reception' } }),
                    axios.get('/api/admin/reports/category-sales', { params }),
                    axios.get('/api/admin/reports/daily-revenue', { params }),
                    axios.get('/api/admin/reports/reception-performance', { params }),
                    axios.get('/api/admin/reports/hourly-orders', { params })
                ]);

                stats.value = statsResponse.data;
                recentOrders.value = ordersResponse.data.orders;
                categorySales.value = categorySalesResponse.data.categories;
                dailyRevenue.value = dailyResponse.data.days;
                receptionPerformance.value = receptionPerformanceResponse.data.receptions;
                hourlyOrders.value = hourlyResponse.data.buckets;
                receptions.value = receptionsResponse.data.users;

                try {
                    const branchesResponse = await axios.get('/api/admin/branches');
                    branches.value = branchesResponse.data.branches;
                } catch {
                    // فیلتر شعبه اختیاری است
                }

                // نمودارها پس از رندر شدن DOM ساخته می‌شوند
                await nextTick();
                createCharts();
            } catch (error) {
                console.error('Error fetching reports data:', error);
                notificationStore.addNotification({
                    type: 'error',
                    title: 'خطا',
                    message: 'خطا در بارگذاری گزارشات'
                });
            } finally {
                loading.value = false;
            }
        };

        const createCharts = () => {
            destroyCharts();

            // نمودار درآمد روزانه
            if (revenueChart.value) {
                const revenueCtx = revenueChart.value.getContext('2d');
                const revenueChartInstance = new Chart(revenueCtx, {
                    type: 'line',
                    data: {
                        labels: dailyRevenue.value.map(day => day.date.slice(5)),
                        datasets: [{
                            label: 'درآمد (تومان)',
                            data: dailyRevenue.value.map(day => day.revenue),
                            borderColor: '#3182ce',
                            backgroundColor: 'rgba(49, 130, 206, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => formatPrice(ctx.parsed.y) + ' تومان'
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: (value) => formatPrice(value)
                                }
                            }
                        }
                    }
                });
                charts.push(revenueChartInstance);
            }

            // نمودار فروش دسته‌بندی‌ها
            if (productsChart.value) {
                const productsCtx = productsChart.value.getContext('2d');
                const productsChartInstance = new Chart(productsCtx, {
                    type: 'doughnut',
                    data: {
                        labels: categorySales.value.length
                            ? categorySales.value.map(item => `${item.icon ?? ''} ${item.name}`.trim())
                            : ['داده‌ای برای نمایش نیست'],
                        datasets: [{
                            label: 'درآمد (تومان)',
                            data: categorySales.value.length
                                ? categorySales.value.map(item => item.total_revenue)
                                : [1],
                            backgroundColor: categorySales.value.length
                                ? categorySales.value.map((_, index) => CHART_COLORS[index % CHART_COLORS.length])
                                : ['#e2e8f0']
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
                charts.push(productsChartInstance);
            }

            // نمودار عملکرد ریسپشن‌ها (تعداد سفارش؛ درآمد در tooltip)
            if (receptionChart.value) {
                const receptionCtx = receptionChart.value.getContext('2d');
                const receptionChartInstance = new Chart(receptionCtx, {
                    type: 'bar',
                    data: {
                        labels: receptionPerformance.value.map(item => item.name),
                        datasets: [{
                            label: 'تعداد سفارشات',
                            data: receptionPerformance.value.map(item => item.orders),
                            backgroundColor: '#805ad5'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    afterLabel: (ctx) => {
                                        const row = receptionPerformance.value[ctx.dataIndex];
                                        return row ? 'درآمد: ' + formatPrice(row.revenue) + ' تومان' : '';
                                    }
                                }
                            }
                        }
                    }
                });
                charts.push(receptionChartInstance);
            }

            // نمودار ساعات پرترافیک (بازه‌های ۲ ساعته)
            if (hoursChart.value) {
                const hoursCtx = hoursChart.value.getContext('2d');
                const hoursChartInstance = new Chart(hoursCtx, {
                    type: 'bar',
                    data: {
                        labels: hourlyOrders.value.map(item => item.label),
                        datasets: [{
                            label: 'تعداد سفارشات',
                            data: hourlyOrders.value.map(item => item.orders),
                            backgroundColor: '#dd6b20'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
                charts.push(hoursChartInstance);
            }
        };

        const destroyCharts = () => {
            charts.forEach(chart => chart.destroy());
            charts = [];
        };

        const handlePeriodChange = () => {
            if (filters.value.period !== 'custom') {
                filters.value.start_date = '';
                filters.value.end_date = '';
            }
            fetchReportsData();
        };

        const refreshData = () => {
            fetchReportsData();
            notificationStore.addNotification({
                type: 'success',
                title: 'بروزرسانی',
                message: 'داده‌ها با موفقیت بروزرسانی شدند'
            });
        };

        const exportReport = async () => {
            try {
                const response = await axios.get('/api/admin/reports/export', {
                    params: { ...filters.value },
                    responseType: 'blob',
                });

                // نام فایل را از هدر Content-Disposition می‌خوانیم
                const disposition = response.headers['content-disposition'] || '';
                const match = disposition.match(/filename="?([^";]+)"?/);
                const filename = match ? match[1] : 'orders.csv';

                const url = URL.createObjectURL(response.data);
                const link = document.createElement('a');
                link.href = url;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(url);

                notificationStore.addNotification({
                    type: 'success',
                    title: 'خروجی',
                    message: 'فایل گزارش دانلود شد'
                });
            } catch (error) {
                console.error('Error exporting report:', error);
                notificationStore.addNotification({
                    type: 'error',
                    title: 'خطا',
                    message: 'خطا در خروجی گرفتن از گزارش'
                });
            }
        };

        const formatChange = (change) => {
            if (!change) return '0%';
            return (change > 0 ? '+' : '') + change + '%';
        };

        const getChangeClass = (change) => {
            if (!change) return 'neutral';
            return change > 0 ? 'positive' : 'negative';
        };

        onMounted(() => {
            fetchReportsData();
        });

        onUnmounted(() => {
            destroyCharts();
        });

        return {
            stats,
            recentOrders,
            receptions,
            branches,
            loading,
            filters,
            revenueChart,
            productsChart,
            receptionChart,
            hoursChart,
            fetchReportsData,
            handlePeriodChange,
            refreshData,
            exportReport,
            formatPrice,
            formatChange,
            getChangeClass
        };
    }
}
</script>

<style scoped>
.reports-page {
    padding: 20px;
    max-width: 1400px;
    margin: 0 auto;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    flex-wrap: wrap;
    gap: 16px;
}

.page-header h2 {
    color: #2d3748;
    font-size: 1.5rem;
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.filters-section {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.filter-row {
    display: flex;
    gap: 20px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-group label {
    color: #4a5568;
    font-weight: 500;
    font-size: 0.875rem;
}

.filter-group select,
.filter-group input {
    padding: 8px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 6px;
    background: white;
    min-width: 150px;
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
    border-left: 4px solid #e2e8f0;
}

.stat-card.primary {
    border-left-color: #3182ce;
}

.stat-card.success {
    border-left-color: #38a169;
}

.stat-card.info {
    border-left-color: #805ad5;
}

.stat-card.warning {
    border-left-color: #dd6b20;
}

.stat-icon {
    font-size: 2.5rem;
    opacity: 0.8;
}

.stat-info {
    flex: 1;
}

.stat-value {
    font-size: 1.875rem;
    font-weight: bold;
    color: #2d3748;
    line-height: 1;
    margin-bottom: 4px;
}

.stat-label {
    color: #718096;
    font-size: 0.875rem;
    margin-bottom: 4px;
}

.stat-change {
    font-size: 0.875rem;
    font-weight: 500;
}

.stat-change.positive {
    color: #38a169;
}

.stat-change.negative {
    color: #e53e3e;
}

.stat-change.neutral {
    color: #718096;
}

.charts-section {
    margin-bottom: 32px;
}

.chart-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 24px;
}

.chart-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.chart-card h3 {
    color: #2d3748;
    margin-bottom: 20px;
    font-size: 1.125rem;
}

.chart-container {
    position: relative;
    height: 300px;
}

.recent-orders {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.recent-orders h3 {
    color: #2d3748;
    margin-bottom: 20px;
    font-size: 1.125rem;
}

.table-container {
    overflow-x: auto;
}

.orders-table {
    width: 100%;
    border-collapse: collapse;
}

.orders-table th,
.orders-table td {
    padding: 12px 16px;
    text-align: right;
    border-bottom: 1px solid #e2e8f0;
}

.orders-table th {
    background: #f7fafc;
    font-weight: 600;
    color: #4a5568;
}

.order-number {
    background: #ebf8ff;
    color: #3182ce;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
}

.price {
    color: #38a169;
    font-weight: 600;
}

.discount {
    color: #dd6b20;
    font-weight: 500;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #a0aec0;
}

.btn-primary {
    background: #3182ce;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    transition: background 0.3s ease;
    white-space: nowrap;
}

.btn-primary:hover {
    background: #2c5aa0;
}

.btn-secondary {
    background: #f7fafc;
    border: 2px solid #e2e8f0;
    color: #4a5568;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.btn-secondary:hover {
    background: #edf2f7;
}

@media (max-width: 1024px) {
    .chart-row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .header-actions {
        justify-content: stretch;
    }
    
    .filter-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>