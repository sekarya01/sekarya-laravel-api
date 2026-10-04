<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Migrasi DATA: room chat task berkuota > 1 yang terlanjur lahir `individual`
 * (perekrutan ditutup dengan satu pekerja) dijadikan `group` — aturan
 * ChatRoomType::forTask (keputusan produk 2026-10-04). Aman diulang.
 * Tanpa `down`: mengembalikannya ke `individual` tidak bisa dibedakan dari
 * room yang memang grup sejak lahir.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('chat_rooms')
            ->where('type', 'individual')
            ->whereIn('task_id', DB::table('tasks')->where('workers_needed', '>', 1)->select('id'))
            ->update(['type' => 'group']);
    }

    public function down(): void
    {
        // Sengaja kosong — lihat catatan di atas.
    }
};
