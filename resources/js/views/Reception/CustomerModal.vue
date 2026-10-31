<template>
    <div v-if="show" class="modal-overlay" @click.self="$emit('close')">
        <div class="modal">
            <div class="modal-header">
                <h3><IconPlus class="emo-icon" aria-hidden="true" /> ثبت مشتری جدید</h3>
                <button @click="$emit('close')" class="close-btn">×</button>
            </div>
            
            <form @submit.prevent="saveCustomer" class="modal-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>نام *</label>
                        <input 
                            type="text" 
                            v-model="form.first_name"
                            required
                            placeholder="نام مشتری"
                            :disabled="loading"
                        >
                    </div>
                    <div class="form-group">
                        <label>نام خانوادگی *</label>
                        <input 
                            type="text" 
                            v-model="form.last_name"
                            required
                            placeholder="نام خانوادگی مشتری"
                            :disabled="loading"
                        >
                    </div>
                </div>
                
                <div class="form-group">
                    <label>شماره تماس *</label>
                    <input 
                        type="tel" 
                        v-model="form.phone"
                        required
                        placeholder="09xxxxxxxxx"
                        pattern="09[0-9]{9}"
                        :disabled="loading"
                    >
                    <small class="input-hint">فرمت: 09xxxxxxxxx</small>
                </div>
                
                <div class="form-group">
                    <label>تاریخ تولد</label>
                    <input 
                        type="date" 
                        v-model="form.date_of_birth"
                        :disabled="loading"
                    >
                </div>

                <div class="form-group consent-group">
                    <label class="consent-label">
                        <input type="checkbox" v-model="form.sms_consent" :disabled="loading">
                        مشتری موافقت کرده است که پیامک‌های اطلاع‌رسانی و پیشنهادهای ویژه دریافت کند
                    </label>
                </div>

                <div v-if="error" class="error-message">
                    {{ error }}
                </div>

                <div class="modal-actions">
                    <button 
                        type="button" 
                        @click="$emit('close')"
                        class="btn-cancel"
                        :disabled="loading"
                    >
                        انصراف
                    </button>
                    <button 
                        type="submit" 
                        :disabled="loading"
                        class="btn-primary"
                    >
                        <span v-if="loading" class="loading-text">
                            در حال ثبت...
                        </span>
                        <span v-else>
                            ثبت مشتری
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
import { Plus as IconPlus } from 'lucide-vue-next';
import { ref } from 'vue';
import axios from 'axios';
import { useNotificationStore } from '../../stores/notifications';

export default {
    components: { IconPlus },
    name: 'CustomerModal',
    props: {
        show: {
            type: Boolean,
            default: false
        }
    },
    emits: ['close', 'saved'],
    setup(props, { emit }) {
        const form = ref({
            first_name: '',
            last_name: '',
            phone: '',
            date_of_birth: '',
            sms_consent: false
        });
        
        const loading = ref(false);
        const error = ref('');

        const notificationStore = useNotificationStore();

        const saveCustomer = async () => {
            if (!form.value.first_name.trim() || !form.value.last_name.trim() || !form.value.phone.trim()) {
                error.value = 'لطفا تمام فیلدهای ضروری را پر کنید';
                return;
            }

            loading.value = true;
            error.value = '';

            try {
                const response = await axios.post('/api/reception/customers', form.value);
                
                notificationStore.addNotification({
                    type: 'success',
                    title: 'موفقیت',
                    message: 'مشتری جدید با موفقیت ثبت شد'
                });

                emit('saved', response.data.customer);
                emit('close');
                
                // ریست فرم
                form.value = {
                    first_name: '',
                    last_name: '',
                    phone: '',
                    date_of_birth: '',
                    sms_consent: false
                };
                
            } catch (err) {
                console.error('Error saving customer:', err);
                error.value = err.response?.data?.message || 'خطا در ثبت مشتری';
                
                notificationStore.addNotification({
                    type: 'error',
                    title: 'خطا',
                    message: error.value
                });
            } finally {
                loading.value = false;
            }
        };

        // ریست فرم وقتی مودال بسته می‌شود
        const resetForm = () => {
            form.value = {
                first_name: '',
                last_name: '',
                phone: '',
                date_of_birth: '',
                sms_consent: false
            };
            error.value = '';
        };

        return {
            form,
            loading,
            error,
            saveCustomer,
            resetForm
        };
    },
    watch: {
        show(newVal) {
            if (!newVal) {
                this.resetForm();
            }
        }
    }
}
</script>

<style scoped>
.consent-label {
    display: flex;
    gap: 8px;
    align-items: flex-start;
    font-size: 0.9rem;
    cursor: pointer;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.input-hint {
    color: #718096;
    font-size: 0.75rem;
    margin-top: 4px;
}

.error-message {
    background: #fed7d7;
    color: #c53030;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 0.875rem;
    margin-bottom: 16px;
}

.loading-text {
    display: flex;
    align-items: center;
    gap: 8px;
}

.loading-text::after {
    content: '';
    width: 16px;
    height: 16px;
    border: 2px solid transparent;
    border-top: 2px solid currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>