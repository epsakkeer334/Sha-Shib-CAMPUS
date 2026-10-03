<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Stores uploads under non-readable, non-predictable names.
 *
 * - File name: 40 random characters from a cryptographically secure source (no timestamp, uniqid,
 *   record id or original name), so a path can neither be guessed nor tell whose file it is.
 * - Folders never contain record ids: files are grouped by type and upload month only
 *   (e.g. students/documents/2026/10/kQ3…x9.pdf). Ownership lives in the database row.
 * - Extension comes from the detected MIME type, not the name the client sent.
 */
class SecureUpload
{
    /**
     * Store the file and return its path on the disk (e.g. "students/documents/2026/10/<random>.pdf").
     */
    public static function store(UploadedFile $file, string $directory, string $disk = 'local'): string
    {
        return $file->storeAs(static::directory($directory), static::fileName($file), $disk);
    }

    /**
     * Store the file and return only the generated file name (for columns that keep just the name
     * and build the URL from a fixed folder, such as institute logos on the public disk).
     */
    public static function storeName(UploadedFile $file, string $directory, string $disk = 'public'): string
    {
        $name = static::fileName($file);
        $file->storeAs(trim($directory, '/'), $name, $disk);

        return $name;
    }

    public static function fileName(UploadedFile $file): string
    {
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: 'bin';

        return Str::random(40) . '.' . $extension;
    }

    protected static function directory(string $directory): string
    {
        return trim($directory, '/') . '/' . now()->format('Y/m');
    }
}
