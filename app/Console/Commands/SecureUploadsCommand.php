<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One-off: move files uploaded before App\Support\SecureUpload to random, non-predictable paths
 * (no record ids in folders, no timestamp/uniqid names) and update the database rows.
 *
 *   php artisan camp:secure-uploads --dry-run   # list what would change
 *   php artisan camp:secure-uploads             # move + update
 *
 * Safe to run again: paths already in the new layout are skipped.
 */
class SecureUploadsCommand extends Command
{
    protected $signature = 'camp:secure-uploads {--dry-run : Only list the files that would be renamed}';

    protected $description = 'Rename existing uploads to non-readable, non-predictable paths';

    /** table => [column, new directory, disk, column holds a full path (true) or only the file name (false)] */
    const TARGETS = [
        ['student_documents', 'file_path', 'students/documents', 'local', true],
        ['student_payments', 'proof_file_path', 'students/payments', 'local', true],
        ['institute_payment_gateways', 'qr_code_path', 'institutes/payment-qr', 'local', true],
        ['institutes', 'logo', 'institutes/logos', 'public', false],
        ['institutes', 'banner', 'institutes/banners', 'public', false],
    ];

    public function handle()
    {
        $dry = (bool) $this->option('dry-run');
        $moved = $missing = 0;

        foreach (self::TARGETS as [$table, $column, $directory, $disk, $fullPath]) {
            $rows = DB::table($table)->whereNotNull($column)->where($column, '!=', '')->get(['id', $column]);

            foreach ($rows as $row) {
                $current = $row->{$column};
                $oldPath = $fullPath ? $current : "{$directory}/{$current}";

                if ($this->alreadySecure($current, $directory, $fullPath)) {
                    continue;
                }
                if (!Storage::disk($disk)->exists($oldPath)) {
                    $missing++;
                    $this->warn("Missing file, skipped: {$table}#{$row->id} {$oldPath}");
                    continue;
                }

                $extension = strtolower(pathinfo($oldPath, PATHINFO_EXTENSION)) ?: 'bin';
                $name = Str::random(40) . '.' . preg_replace('/[^a-z0-9]/', '', $extension);
                $newPath = $fullPath ? "{$directory}/" . now()->format('Y/m') . "/{$name}" : "{$directory}/{$name}";

                $this->line(($dry ? '[dry-run] ' : '') . "{$table}#{$row->id}: {$oldPath} → {$newPath}");
                if ($dry) {
                    $moved++;
                    continue;
                }

                Storage::disk($disk)->move($oldPath, $newPath);
                DB::table($table)->where('id', $row->id)->update([$column => $fullPath ? $newPath : $name]);
                $moved++;
            }
        }

        $this->info(($dry ? 'Would rename' : 'Renamed') . " {$moved} file(s)" . ($missing ? ", {$missing} missing" : '') . '.');

        return self::SUCCESS;
    }

    /** New layout: <directory>/[YYYY/MM/]<40 random chars>.<ext> */
    protected function alreadySecure(string $value, string $directory, bool $fullPath): bool
    {
        $pattern = $fullPath
            ? '#^' . preg_quote($directory, '#') . '/\d{4}/\d{2}/[A-Za-z0-9]{40}\.[a-z0-9]+$#'
            : '#^[A-Za-z0-9]{40}\.[a-z0-9]+$#';

        return (bool) preg_match($pattern, $value);
    }
}
