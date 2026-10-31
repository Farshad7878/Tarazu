<template>
    <div class="sms-page">
        <div class="page-header">
            <h2><IconMegaphone class="emo-icon" aria-hidden="true" /> پیامک گروهی</h2>
            <span class="limit-note">سقف ارسال روزانه: {{ dailyLimit }} پیامک</span>
        </div>

        <div v-if="smsLive === false" class="sim-warning">
            <IconTriangleAlert class="emo-icon" aria-hidden="true" /> درایور پیامک روی <strong>{{ smsDriver }}</strong> است و هیچ پیامکی به مشتری ارسال نمی‌شود.
            کمپین‌ها فقط شبیه‌سازی می‌شوند؛ آمار «شبیه‌سازی‌شده» است و «ارسال‌شده» نیست.
            برای ارسال واقعی، SMS_DRIVER=kavenegar و کلید API را تنظیم کنید.
        </div>

        <div class="card">
            <h3>کمپین جدید</h3>
            <p class="hint">
                فقط به مشتریانی پیامک ارسال می‌شود که موافقت دریافت پیامک دارند و لغو اشتراک نکرده‌اند.
                لینک لغو اشتراک به‌صورت خودکار به انتهای هر پیامک اضافه می‌شود.
            </p>

            <form class="campaign-form" @submit.prevent="storeCampaign">
                <div class="row">
                    <div class="field">
                        <label>نام کمپین</label>
                        <input type="text" maxlength="120" v-model="form.name" required :disabled="busy">
                    </div>
                    <div class="field">
                        <label>گروه مخاطب</label>
                        <select v-model="form.segment_type" :disabled="busy" @change="preview = null">
                            <option value="all">همه‌ی مشتریان واجد شرایط</option>
                            <option value="inactive">مشتریان غیرفعال</option>
                            <option value="big_spenders">خریداران پرخرج</option>
                        </select>
                    </div>
                    <div v-if="form.segment_type === 'inactive'" class="field">
                        <label>بدون خرید در ... روز گذشته</label>
                        <input type="number" min="1" max="3650" v-model.number="form.segment_days" required :disabled="busy" @input="preview = null">
                    </div>
                    <div v-if="form.segment_type === 'big_spenders'" class="field">
                        <label>حداقل مجموع خرید (تومان)</label>
                        <input type="number" min="0" step="1" v-model.number="form.segment_min_total" required :disabled="busy" @input="preview = null">
                    </div>
                </div>

                <div class="field">
                    <label>متن پیامک ({{ form.message.length }}/400)</label>
                    <textarea maxlength="400" rows="4" v-model="form.message" required :disabled="busy"></textarea>
                </div>

                <div class="actions">
                    <button type="button" class="btn-secondary" :disabled="busy" @click="previewAudience">
                        پیش‌نمایش تعداد گیرندگان
                    </button>
                    <span v-if="preview !== null" class="preview-count">
                        {{ preview }} مشتری واجد شرایط
                    </span>
                    <button type="submit" class="btn-primary" :disabled="busy">ثبت و زمان‌بندی ارسال</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h3>کمپین‌ها</h3>
            <div class="table-scroll" v-if="campaigns.length"><table class="campaigns-table">
                <thead>
                    <tr>
                        <th>نام</th>
                        <th>گروه</th>
                        <th>وضعیت</th>
                        <th>گیرندگان</th>
                        <th>ارسال‌شده</th>
                        <th>ناموفق</th>
                        <th>رده‌شده</th>
                        <th>شبیه‌سازی</th>
                        <th>ثبت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in campaigns" :key="c.id">
                        <td>
                            <strong>{{ c.name }}</strong>
                            <div class="msg">{{ c.message }}</div>
                        </td>
                        <td>{{ c.segment_label }}</td>
                        <td><span :class="['status', 'status-' + c.status]">{{ c.status_label }}</span></td>
                        <td>{{ c.total_recipients }}</td>
                        <td>{{ c.sent_count }}</td>
                        <td>{{ c.failed_count }}</td>
                        <td>{{ c.skipped_count }}</td>
                        <td>{{ c.simulated_count }}</td>
                        <td>{{ c.created_at }}<div class="muted">{{ c.created_by || '' }}</div></td>
                        <td>
                            <button v-if="c.status === 'active'" class="btn-danger small" :disabled="busy" @click="cancelCampaign(c)">لغو</button>
                        </td>
                    </tr>
                </tbody>
            </table></div>
            <div v-else class="empty">هنوز کمپینی ثبت نشده است.</div>
        </div>

        <div v-if="message" :class="['toast', messageType]">{{ message }}</div>
    </div>
</template>

<script>
import { Megaphone as IconMegaphone, TriangleAlert as IconTriangleAlert } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import axios from 'axios';

const emptyForm = () => ({
    name: '',
    message: '',
    segment_type: 'all',
    segment_days: 90,
    segment_min_total: 0,
});

export default {
    components: { IconMegaphone, IconTriangleAlert },
    name: 'AdminSmsCampaigns',
    setup() {
        const campaigns = ref([]);
        const dailyLimit = ref(0);
        const smsLive = ref(null);
        const smsDriver = ref('');
        const form = ref(emptyForm());
        const preview = ref(null);
        const busy = ref(false);
        const message = ref('');
        const messageType = ref('success');

        const notify = (text, type = 'success') => {
            message.value = text;
            messageType.value = type;
            setTimeout(() => { message.value = ''; }, 4500);
        };

        const errorText = (error) => {
            const errors = error.response?.data?.errors;
            return (errors && Object.values(errors).flat()[0]) || error.response?.data?.message || 'خطایی رخ داد';
        };

        const segmentPayload = () => ({
            segment_type: form.value.segment_type,
            segment_days: form.value.segment_type === 'inactive' ? form.value.segment_days : null,
            segment_min_total: form.value.segment_type === 'big_spenders' ? form.value.segment_min_total : null,
        });

        const fetchCampaigns = async () => {
            try {
                const response = await axios.get('/api/admin/sms-campaigns');
                campaigns.value = response.data.campaigns;
                dailyLimit.value = response.data.daily_limit;
                smsLive.value = response.data.sms_live;
                smsDriver.value = response.data.sms_driver;
            } catch (error) {
                notify(errorText(error), 'error');
            }
        };

        const previewAudience = async () => {
            busy.value = true;
            try {
                const response = await axios.post('/api/admin/sms-campaigns/preview', segmentPayload());
                preview.value = response.data.count;
            } catch (error) {
                notify(errorText(error), 'error');
            } finally {
                busy.value = false;
            }
        };

        const storeCampaign = async () => {
            if (!window.confirm('کمپین برای مشتریان واجد شرایط در صف ارسال قرار گیرد؟')) return;
            busy.value = true;
            try {
                const response = await axios.post('/api/admin/sms-campaigns', {
                    name: form.value.name,
                    message: form.value.message,
                    ...segmentPayload(),
                });
                notify(response.data.message);
                form.value = emptyForm();
                preview.value = null;
                await fetchCampaigns();
            } catch (error) {
                notify(errorText(error), 'error');
            } finally {
                busy.value = false;
            }
        };

        const cancelCampaign = async (campaign) => {
            if (!window.confirm(`کمپین «${campaign.name}» لغو شود؟ پیامک‌های ارسال‌نشده متوقف می‌شوند.`)) return;
            busy.value = true;
            try {
                const response = await axios.post(`/api/admin/sms-campaigns/${campaign.id}/cancel`);
                notify(response.data.message);
                await fetchCampaigns();
            } catch (error) {
                notify(errorText(error), 'error');
            } finally {
                busy.value = false;
            }
        };

        onMounted(fetchCampaigns);

        return {
            campaigns, dailyLimit, smsLive, smsDriver, form, preview, busy, message, messageType,
            previewAudience, storeCampaign, cancelCampaign,
        };
    },
};
</script>

<style scoped>
.sms-page { padding: 24px; }
.limit-note { color: #718096; font-size: 0.9rem; }
.sim-warning { background: #fffaf0; border: 1px solid #f6ad55; color: #7b341e; border-radius: 8px; padding: 10px 14px; margin-bottom: 12px; line-height: 1.8; }
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; margin-bottom: 16px; }
.card h3 { margin: 0 0 8px; }
.hint { color: #718096; font-size: 0.88rem; margin-bottom: 12px; }
.campaign-form { display: flex; flex-direction: column; gap: 12px; }
.row { display: flex; gap: 12px; flex-wrap: wrap; }
.field { display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 200px; font-size: 0.9rem; }
.field input, .field select, .field textarea { padding: 8px 10px; border: 1px solid #cbd5e0; border-radius: 6px; font-family: inherit; }
.actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.preview-count { color: #2c5282; font-weight: 600; }
.btn-danger.small { padding: 5px 10px; font-size: 0.85rem; }
button:disabled { opacity: 0.6; cursor: not-allowed; }
.campaigns-table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
.campaigns-table th, .campaigns-table td { padding: 9px; border-bottom: 1px solid #edf2f7; text-align: right; vertical-align: top; }
.campaigns-table th { background: #f7fafc; color: #4a5568; }
.msg { color: #718096; font-size: 0.8rem; max-width: 260px; white-space: pre-wrap; }
.muted { color: #a0aec0; font-size: 0.8rem; }
.status { padding: 3px 8px; border-radius: 999px; font-size: 0.78rem; background: #edf2f7; }
.status-active { background: #bee3f8; color: #2a4365; }
.status-completed { background: #c6f6d5; color: #22543d; }
.status-cancelled { background: #fed7d7; color: #742a2a; }
.empty { padding: 24px; text-align: center; color: #718096; }
.toast { position: fixed; bottom: 20px; left: 20px; padding: 12px 16px; border-radius: 8px; color: #fff; }
.toast.success { background: #2f855a; }
.toast.error { background: #c53030; }
</style>
