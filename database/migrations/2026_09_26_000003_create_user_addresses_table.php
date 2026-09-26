<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alamat tersimpan (satu per orang) — B6.
 *
 * Tabel sendiri, bukan kolom di `users`: `users.address_line/city` adalah
 * DOMISILI yang ikut terbaca di profil, sedangkan ini alamat pribadi yang
 * dipakai mengisi lokasi tugas ("Pakai alamat tersimpan") dan TIDAK PERNAH
 * keluar ke orang lain. Memisahkannya membuat `PublicUserResource` secara
 * harfiah tidak punya jalan untuk membocorkannya.
 *
 * `user_id` unique = 1:1 dijamin basis data; `PUT me/address` memakai
 * upsert di atas kunci itu. Koordinat nullable tapi berpasangan (dijaga
 * FormRequest), presisi 7 desimal menyamai `tasks`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('label', 40)->nullable();
            $table->string('address_line', 250);
            $table->string('city', 80);
            $table->string('province', 80)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};
