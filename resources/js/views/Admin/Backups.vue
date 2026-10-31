<template>
    <div class="backup-page">
        <div class="page-header">
            <div>
                <h2><IconSave class="emo-icon" aria-hidden="true" /> پشتیبان‌گیری و بازیابی</h2>
                <p class="muted">پشتیبان خودکار هر شب ساعت ۰۲:۳۰ ساخته می‌شود. نگه‌داری: {{ keep }} نسخه‌ی آخر.</p>
            </div>
            <button class="btn-primary" :disabled="working" @click="createBackup"><IconPlus class="emo-icon" aria-hidden="true" /> پشتیبان جدید</button>
        </div>

        <div v-if="message" :class="['flash', messageType]">{{ message }}</div>

        <div class="table-container">
            <div class="table-scroll" v-if="backups.length"><table class="data-table">
                <thead>
                    <tr>
                        <th>نام فایل</th>
                        <th>حجم</th>
                        <th>تاریخ</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="b in backups" :key="b.name">
                        <td class="code">{{ b.name }}</td>
                        <td>{{ sizeText(b.size) }}</td>
                        <td>{{ b.created_at }}</td>
                        <td class="actions">
                            <button class="btn-small" :disabled="working" @click="download(b)">دانلود</button>
                            <button class="btn-small btn-warn" :disabled="working" @click="openRestore(b)">بازیابی</button>
                            <button class="btn-small btn-danger" :disabled="working" @click="remove(b)">حذف</button>
                        </td>
                    </tr>
                </tbody>
            </table></div>
            <div v-else-if="!loading" class="empty">هنوز پشتیبانی ساخته نشده است.</div>
            <div v-if="loading" class="empty">در حال بارگذاری...</div>
        </div>

        <div class="notice">
            <IconTriangleAlert class="emo-icon" aria-hidden="true" /> بازیابی، کل اطلاعات فعلی را با محتوای پشتیبان جایگزین می‌کند. پیش از بازیابی، خودکار یک پشتیبان از وضعیت فعلی گرفته می‌شود.
            گزارش فعالیت‌ها (Audit Log) هم با پشتیبان جایگزین می‌شود، بنابراین رویدادهای بعد از زمان پشتیبان از آن حذف می‌شوند؛ ثبت «بازیابی پشتیبان» پس از بازیابی نوشته می‌شود.
        </div>

        <div v-if="restoreTarget" class="modal-overlay" @click.self="closeRestore">
            <div class="modal">
                <h3>بازیابی از {{ restoreTarget.name }}</h3>
                <p>برای ادامه، عبارت <strong>RESTORE</strong> را وارد کنید. اطلاعات فعلی جایگزین می‌شود.</p>
                <input v-model="confirmText" placeholder="RESTORE" class="confirm-input">
                <div class="modal-footer">
                    <button class="btn-cancel" @click="closeRestore">انصراف</button>
                    <button class="btn-danger" :disabled="confirmText !== 'RESTORE' || working" @click="restore">بازیابی</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { Plus as IconPlus, Save as IconSave, TriangleAlert as IconTriangleAlert } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import axios from 'axios';

export default {
    components: { IconPlus, IconSave, IconTriangleAlert },
    name: 'Backups',
    setup() {
        const backups = ref([]);
        const keep = ref(14);
        const loading = ref(false);
        const working = ref(false);
        const message = ref('');
        const messageType = ref('success');
        const restoreTarget = ref(null);
        const confirmText = ref('');

        const sizeText = (bytes) => {
            if (bytes < 1024) return `${bytes} B`;
            if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
            return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
        };

        const flash = (text, type = 'success') => {
            message.value = text;
            messageType.value = type;
            setTimeout(() => {
                if (message.value === text) message.value = '';
            }, 8000);
        };

        const errorText = (error, fallback) => {
            const first = Object.values(error.response?.data?.errors || {}).flat()[0];
            return error.response?.data?.message || first || fallback;
        };

        const fetchBackups = async () => {
            loading.value = true;
            try {
                const response = await axios.get('/api/admin/backups');
                backups.value = response.data.backups;
                keep.value = response.data.keep;
            } catch (error) {
                flash(errorText(error, 'خطا در بارگذاری فهرست پشتیبان‌ها'), 'error');
            } finally {
                loading.value = false;
            }
        };

        const createBackup = async () => {
            working.value = true;
            try {
                const response = await axios.post('/api/admin/backups');
                flash(`${response.data.message} (${response.data.backup.name})`);
                await fetchBackups();
            } catch (error) {
                flash(errorText(error, 'ساخت پشتیبان انجام نشد'), 'error');
            } finally {
                working.value = false;
            }
        };

        // دانلود با توکن (لینک مستقیم، هدر Authorization را نمی‌فرستد)
        const download = async (backup) => {
            working.value = true;
            try {
                const response = await axios.get(`/api/admin/backups/${encodeURIComponent(backup.name)}/download`, { responseType: 'blob' });
                const url = URL.createObjectURL(response.data);
                const link = document.createElement('a');
                link.href = url;
                link.download = backup.name;
                document.body.appendChild(link);
                link.click();
                link.remove();
                setTimeout(() => URL.revokeObjectURL(url), 1000);
            } catch (error) {
                flash(errorText(error, 'دانلود انجام نشد'), 'error');
            } finally {
                working.value = false;
            }
        };

        const remove = async (backup) => {
            if (!window.confirm(`پشتیبان ${backup.name} حذف شود؟`)) return;
            working.value = true;
            try {
                await axios.delete(`/api/admin/backups/${encodeURIComponent(backup.name)}`);
                flash('پشتیبان حذف شد.');
                await fetchBackups();
            } catch (error) {
                flash(errorText(error, 'حذف انجام نشد'), 'error');
            } finally {
                working.value = false;
            }
        };

        const openRestore = (backup) => {
            restoreTarget.value = backup;
            confirmText.value = '';
        };

        const closeRestore = () => {
            restoreTarget.value = null;
            confirmText.value = '';
        };

        const restore = async () => {
            working.value = true;
            try {
                const response = await axios.post('/api/admin/backups/restore', {
                    name: restoreTarget.value.name,
                    confirm: confirmText.value,
                });
                closeRestore();
                flash(`${response.data.message} پشتیبان ایمنی قبل از بازیابی: ${response.data.result?.safety_backup || '—'}`);
                await fetchBackups();
            } catch (error) {
                flash(errorText(error, 'بازیابی انجام نشد'), 'error');
            } finally {
                working.value = false;
            }
        };

        onMounted(fetchBackups);

        return {
            backups, keep, loading, working, message, messageType, restoreTarget, confirmText,
            sizeText, createBackup, download, remove, openRestore, closeRestore, restore,
        };
    },
};
</script>

<style scoped>
.backup-page { padding: 24px; }
.muted { color: #718096; font-size: 0.85rem; margin: 0; }
.flash { padding: 10px 14px; border-radius: 8px; margin-bottom: 12px; }
.flash.success { background: #f0fff4; color: #276749; border: 1px solid #9ae6b4; }
.flash.error { background: #fff5f5; color: #9b2c2c; border: 1px solid #feb2b2; }
.table-container { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.data-table th, .data-table td { padding: 10px; border-bottom: 1px solid #edf2f7; text-align: right; white-space: nowrap; }
.data-table th { background: #f7fafc; color: #4a5568; }
.code { font-family: monospace; direction: ltr; text-align: left; }
.actions { display: flex; gap: 6px; }
.btn-warn { color: #975a16; border-color: #f6e05e; }
.empty { padding: 30px; text-align: center; color: #718096; }
.notice { margin-top: 14px; padding: 12px; background: #fffaf0; border: 1px solid #f6ad55; border-radius: 8px; font-size: 0.88rem; line-height: 1.8; }
.confirm-input { width: 100%; padding: 8px; border: 1px solid #cbd5e0; border-radius: 6px; direction: ltr; }
</style>
