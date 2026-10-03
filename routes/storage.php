<?php

declare(strict_types=1);

use App\Http\Controllers\Storage\ShowChatAttachmentController;
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
 *   - hanya folder yang memang dipublikasikan StoreUploadController (foto
 *     task, avatar, foto bukti kerja);
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
    ->where('folder', 'tasks|avatars|proofs')
    ->where('file', '[A-Za-z0-9]{1,100}\.(jpe?g|png|webp)')
    ->name('storage.public-upload');

/*
 * Lampiran chat: `uploads/chat/{room}/{file}` (StoreChatAttachmentAction).
 *
 *   - room = ULID room (26 karakter Crockford, huruf besar), bukan path bebas;
 *   - nama berkas = `hashName` (huruf/angka) + ekstensi huruf/angka 2–5 —
 *     tanpa titik ganda, garis miring, atau `%`, jadi `..` tidak terbentuk.
 *     Ekstensi tidak dibatasi daftar `sekarya.chat.extensions` karena
 *     `hashName` memakai ekstensi TEBAKAN dari MIME (mp3 → `mpga`, m4a →
 *     `mp4`), yang bisa di luar daftar itu. Isinya hanya berkas yang lolos
 *     validasi unggah chat.
 */
Route::get('storage/uploads/chat/{room}/{file}', ShowChatAttachmentController::class)
    ->where('room', '[0-9A-HJKMNP-TV-Z]{26}')
    ->where('file', '[A-Za-z0-9]{1,100}\.[A-Za-z0-9]{2,5}')
    ->name('storage.chat-attachment');
