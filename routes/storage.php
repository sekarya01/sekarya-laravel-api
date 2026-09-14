<?php

declare(strict_types=1);

use App\Http\Controllers\Storage\ShowPublicUploadController;
use Illuminate\Support\Facades\Route;

/*
 * Unggahan publik, dilayani aplikasi — lihat ShowPublicUploadController.
 *
 * Sengaja di luar grup `web`: gambar tidak butuh sesi, dan middleware sesi
 * akan menempelkan Set-Cookie ke setiap respons gambar, yang membuat CDN dan
 * cache HTTP menolak menyimpannya.
 *
 * Constraint parameter adalah pagar keamanannya, jadi dibuat sesempit mungkin:
 *
 *   - hanya dua folder yang memang dipublikasikan StoreUploadController;
 *     disk `public` tidak boleh terbuka seluruhnya lewat URL ini
 *   - nama berkas hanya huruf dan angka — tanpa titik, garis miring, atau
 *     `%` — sehingga `..` dan varian ter-encode-nya tidak bisa terbentuk
 *   - hanya ekstensi yang diterima StoreUploadRequest
 *
 * Path yang tidak cocok jatuh ke route `storage.local` bawaan Laravel, yang
 * menolaknya karena tidak bertanda tangan.
 *
 * URI-nya WAJIB berbeda dari `storage/{path}`. Route itu didaftarkan Laravel
 * untuk disk `local` (`serve => true`), dan koleksi route diindeks per URI:
 * route kedua dengan URI yang sama diam-diam menimpa yang pertama, tanpa
 * galat. Dengan URI berbeda keduanya hidup berdampingan, dan route ini
 * dicocokkan lebih dulu karena didaftarkan lebih awal.
 */
Route::get('storage/uploads/{folder}/{file}', ShowPublicUploadController::class)
    ->where('folder', 'tasks|avatars')
    ->where('file', '[A-Za-z0-9]{1,100}\.(jpe?g|png|webp)')
    ->name('storage.public-upload');
