<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * پشتیبان‌گیری و بازیابی کل داده‌ها به‌صورت JSON (فشرده با gzip در صورت پشتیبانی).
 *
 * - مستقل از نوع پایگاه داده (SQLite یا MySQL) و بدون ابزار خارجی.
 * - جدول‌های نشست، کش، صف و توکن‌های API پشتیبان‌گیری نمی‌شوند.
 * - بازیابی فقط وقتی مجاز است که مهاجرت‌های پشتیبان با برنامه‌ی فعلی یکسان باشند.
 * - قبل از هر بازیابی، خودکار یک پشتیبان «pre-restore» گرفته می‌شود.
 */
class BackupService
{
    public const NAME_PATTERN = '/^backup-\d{8}-\d{6}(?:-[a-z0-9-]+)?\.json(?:\.gz)?$/';

    /** تاریخچه‌ای که هنگام بازیابی حذف نمی‌شود: ردیف‌های فعلی حفظ و ردیف‌های گمشده از پشتیبان اضافه می‌شوند */
    private const MERGED_TABLES = ['audit_logs'];

    private const EXCLUDED_TABLES = [
        'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs',
        'personal_access_tokens', 'password_reset_tokens', 'sqlite_sequence',
    ];

    public static function directory(): string
    {
        return storage_path('app/backups');
    }

    /**
     * @return array{name: string, size: int, created_at: string, tables: int, rows: int}
     */
    public function create(string $label = 'manual'): array
    {
        File::ensureDirectoryExists(self::directory());

        $base = 'backup-'.now()->format('Ymd-His').'-'.$label;
        $name = $base.(function_exists('gzopen') ? '.json.gz' : '.json');
        if (File::exists(self::directory().'/'.$name)) {
            $name = 'backup-'.now()->format('Ymd-His').'-'.$label.'-'.Str::lower(Str::random(4))
                .(function_exists('gzopen') ? '.json.gz' : '.json');
        }

        $path = self::directory().'/'.$name;
        $compressed = str_ends_with($name, '.gz');
        $handle = $compressed ? gzopen($path, 'wb9') : fopen($path, 'wb');

        if ($handle === false) {
            throw new \RuntimeException('ایجاد فایل پشتیبان ممکن نیست.');
        }

        $write = fn (string $chunk) => $compressed ? gzwrite($handle, $chunk) : fwrite($handle, $chunk);

        $tables = $this->backupTables();
        $rowTotal = 0;

        $write('{"meta":'.json_encode([
            'app' => config('app.name'),
            'created_at' => now()->toIso8601String(),
            'migrations' => DB::table('migrations')->orderBy('id')->pluck('migration')->all(),
        ], JSON_UNESCAPED_UNICODE).',"tables":{');

        foreach ($tables as $i => $table) {
            $write(($i ? ',' : '').json_encode($table).':[');

            $first = true;
            foreach (DB::table($table)->cursor() as $row) {
                $write(($first ? '' : ',').json_encode((array) $row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
                $first = false;
                $rowTotal++;
            }

            $write(']');
        }

        $write('}}');

        $compressed ? gzclose($handle) : fclose($handle);

        $this->prune();

        return [
            'name' => $name,
            'size' => (int) File::size($path),
            'created_at' => now()->format('Y-m-d H:i'),
            'tables' => count($tables),
            'rows' => $rowTotal,
        ];
    }

    /**
     * @return array<int, array{name: string, size: int, created_at: string, compressed: bool}>
     */
    public function list(): array
    {
        if (! File::isDirectory(self::directory())) {
            return [];
        }

        return collect(File::files(self::directory()))
            ->filter(fn ($file) => preg_match(self::NAME_PATTERN, $file->getFilename()))
            ->sortByDesc(fn ($file) => $file->getFilename())
            ->values()
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'size' => (int) $file->getSize(),
                'created_at' => date('Y-m-d H:i', $file->getMTime()),
                'compressed' => str_ends_with($file->getFilename(), '.gz'),
            ])
            ->all();
    }

    public function path(string $name): string
    {
        if (! preg_match(self::NAME_PATTERN, $name)) {
            throw ValidationException::withMessages(['name' => ['نام فایل پشتیبان معتبر نیست.']]);
        }

        $path = self::directory().'/'.$name;

        if (! File::exists($path)) {
            throw ValidationException::withMessages(['name' => ['فایل پشتیبان پیدا نشد.']]);
        }

        return $path;
    }

    public function delete(string $name): void
    {
        File::delete($this->path($name));
    }

    /**
     * بازیابی کامل از یک پشتیبان. داده‌های فعلی جدول‌های مشترک جایگزین می‌شوند.
     *
     * @return array{tables: int, rows: int, safety_backup: string}
     */
    public function restore(string $name): array
    {
        $path = $this->path($name);
        $payload = $this->read($path);

        $dumpMigrations = $payload['meta']['migrations'] ?? null;
        $currentMigrations = DB::table('migrations')->orderBy('id')->pluck('migration')->all();

        if (! is_array($dumpMigrations) || $dumpMigrations !== $currentMigrations) {
            throw ValidationException::withMessages([
                'name' => ['ساختار پایگاه داده‌ی این پشتیبان با نسخه‌ی فعلی برنامه یکسان نیست و قابل بازیابی نیست.'],
            ]);
        }

        // ایمنی: قبل از هر بازیابی یک نسخه‌ی کامل از وضعیت فعلی گرفته می‌شود
        $safety = $this->create('pre-restore');

        $tables = $payload['tables'] ?? [];
        $rowTotal = 0;

        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($tables, &$rowTotal) {
                foreach ($tables as $table => $rows) {
                    if (! Schema::hasTable($table) || in_array($table, self::EXCLUDED_TABLES, true)) {
                        continue;
                    }

                    $columns = array_flip(Schema::getColumnListing($table));
                    $merge = in_array($table, self::MERGED_TABLES, true);

                    // جدول‌های ادغامی (audit_logs) پاک نمی‌شوند؛ ردیف‌های پشتیبان که قبلاً وجود ندارند اضافه می‌شوند
                    if (! $merge) {
                        DB::table($table)->delete();
                    }

                    foreach (array_chunk($rows, 500) as $chunk) {
                        $clean = array_map(fn (array $row) => array_intersect_key($row, $columns), $chunk);
                        $merge ? DB::table($table)->insertOrIgnore($clean) : DB::table($table)->insert($clean);
                    }

                    $rowTotal += count($rows);
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return [
            'tables' => count($tables),
            'rows' => $rowTotal,
            'safety_backup' => $safety['name'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function backupTables(): array
    {
        return collect(Schema::getTableListing())
            ->map(fn ($table) => str_contains($table, '.') ? explode('.', $table)[1] : $table)
            ->reject(fn ($table) => in_array($table, self::EXCLUDED_TABLES, true) || str_starts_with($table, 'sqlite_'))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function read(string $path): array
    {
        $raw = str_ends_with($path, '.gz')
            ? (function_exists('gzdecode') ? gzdecode(file_get_contents($path)) : false)
            : file_get_contents($path);

        $data = $raw === false ? null : json_decode($raw, true);

        if (! is_array($data) || ! isset($data['tables'])) {
            throw ValidationException::withMessages(['name' => ['فایل پشتیبان خراب است یا قابل خواندن نیست.']]);
        }

        return $data;
    }

    /**
     * نگه داشتن فقط به تعداد تنظیم‌شده (جدیدترین‌ها)
     */
    private function prune(): void
    {
        $keep = max(1, (int) config('backup.keep', 14));

        collect($this->list())
            ->slice($keep)
            ->each(fn (array $file) => File::delete(self::directory().'/'.$file['name']));
    }
}
