<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Penanganan upload image terpusat untuk API.
 *
 * Konvensi (mengikuti Filament admin panel):
 * - Disk: `public` (storage/app/public, diserve lewat symlink /storage).
 * - Yang DISIMPAN di DB adalah PATH RELATIF (mis. `menu/images/abc.jpg`),
 *   bukan URL penuh — agar portabel antar domain.
 * - Saat dibaca via Resource, path dikonversi ke URL penuh dengan toUrl().
 */
class MediaService
{
    /**
     * Disk penyimpanan publik.
     */
    private const DISK = 'public';

    /**
     * Simpan file upload ke direktori tertentu pada disk publik.
     *
     * @return string Path relatif yang akan disimpan di kolom model.
     */
    public function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, self::DISK);
    }

    /**
     * Hapus file lama dari disk publik bila ada.
     *
     * Aman dipanggil dengan null. Nilai berupa URL absolut (http/https)
     * di-skip — itu kemungkinan data lama yang di-host di luar (tidak dikelola).
     */
    public function delete(?string $path): void
    {
        if (empty($path) || $this->isAbsoluteUrl($path)) {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }

    /**
     * Ganti file: simpan yang baru, hapus yang lama, kembalikan path baru.
     */
    public function replace(UploadedFile $file, string $directory, ?string $oldPath): string
    {
        $newPath = $this->store($file, $directory);
        $this->delete($oldPath);

        return $newPath;
    }

    /**
     * Konversi path tersimpan menjadi URL penuh yang siap dipakai frontend/mobile.
     *
     * - null → null.
     * - URL absolut (data lama) → dikembalikan apa adanya.
     * - Path relatif → URL penuh disk publik.
     */
    public static function toUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return Storage::disk(self::DISK)->url($path);
    }

    private function isAbsoluteUrl(string $value): bool
    {
        return Str::startsWith($value, ['http://', 'https://']);
    }
}
