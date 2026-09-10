<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengelola aplikasi — TABEL SENDIRI, bukan kolom `role` di `users`.
 *
 * Alasannya bukan kerapian, tapi permukaan serangan. `users` adalah tabel yang
 * bisa disentuh publik: pendaftaran menulis ke sana, profil menyunting sendiri,
 * dan pemulihan kata sandi mengambil baris dari sana lewat alamat email.
 * Menaruh kewenangan pengelola sebagai satu nilai kolom di tabel itu berarti
 * setiap kebocoran mass-assignment di seluruh alur pengguna berpotensi menjadi
 * kenaikan hak akses. Tabel terpisah tidak punya satu pun jalur tulis publik.
 *
 * Konsekuensi yang HARUS ikut, dan gampang terlupa: `auth:sanctum` tidak
 * membedakan jenis pemilik token. Sanctum mendaftarkan guard-nya sendiri
 * dengan `provider => null`, dan `Guard::hasValidProvider()` langsung
 * mengembalikan true kalau provider null — jadi begitu ada model kedua yang
 * memegang token, token admin lolos di seluruh endpoint pengguna dan
 * sebaliknya. Karena itu `config/auth.php` di proyek ini menyebut provider
 * setiap guard secara eksplisit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();

            $table->string('name', 120);
            $table->string('email', 180)->unique();
            $table->string('password');

            /*
             * Dua kolom pembawa hak akses. Keduanya:
             *
             *   1. TIDAK fillable (lihat App\Models\Admin)
             *   2. default-nya keadaan paling sedikit hak
             *
             * Kedua aturan itu harus berlaku bersamaan. Nilai yang jatuh dari
             * mass assignment hilang TANPA galat, sehingga default kolom yang
             * menentukan hasilnya — bug ini sudah pernah terjadi di `users`,
             * dan di tabel ini akibatnya adalah super_admin yang lahir sendiri.
             *
             * Jadi: role bawaan `admin` (bukan super_admin), status bawaan
             * `suspended` (belum bisa masuk). Akun yang lolos tanpa disengaja
             * adalah akun terkunci tanpa kewenangan, bukan pemilik penuh.
             */
            $table->string('role', 24)->default('admin');
            $table->string('status', 24)->default('suspended');

            /*
             * SATU super_admin, dijamin basis data.
             *
             * MySQL tidak punya partial index, jadi trik-nya kolom turunan:
             * ia berisi 's' hanya untuk baris super_admin dan NULL untuk
             * sisanya, dan indeks unique MySQL tidak menganggap dua NULL
             * bertabrakan. Hasilnya "paling banyak satu baris super_admin"
             * ditegakkan server, bukan disiplin kode.
             *
             * Turunan (GENERATED), bukan kolom biasa yang diisi aplikasi:
             * kolom biasa bisa melenceng dari `role` lewat satu UPDATE manual
             * di phpMyAdmin, dan penjaganya ikut hilang tanpa suara.
             */
            $table->char('super_admin_lock', 1)
                ->storedAs("case when role = 'super_admin' then 's' end")
                ->nullable();

            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique('super_admin_lock');
            $table->index(['status', 'role']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
