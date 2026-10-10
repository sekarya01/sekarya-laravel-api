<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sengketa per MITRA, bukan per task.
 *
 * Komplain pemberi kerja selalu tentang hasil kerja satu orang. Dengan tiket
 * per task, keputusan pengelola mengenai semua mitra sekaligus: "kembalikan"
 * menarik upah mitra yang kerjanya baik, "lepas" membayar yang dikomplain.
 * Tiket kini menunjuk satu activity, dan keputusannya hanya berlaku untuknya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_disputes', function (Blueprint $table): void {
            $table->foreignId('activity_id')->nullable()->after('task_id')
                ->constrained('activities')->cascadeOnDelete();
            // Kategori alasan (DisputeCategory). NULL hanya untuk tiket lama.
            $table->string('category', 16)->nullable()->after('raised_by');
            // Tanggapan mitra — satu kali, pengelola mendengar kedua pihak.
            $table->string('worker_response', 500)->nullable()->after('evidence_photos');
            $table->json('worker_evidence_photos')->nullable()->after('worker_response');
            $table->timestamp('worker_responded_at')->nullable()->after('worker_evidence_photos');

            // Satu tiket TERBUKA per activity, ditegakkan server — pola yang
            // sama dengan `admins.super_admin_lock`: MySQL tak punya partial
            // index, jadi kolom turunan berisi activity_id hanya selama tiket
            // terbuka, dan unique tidak menganggap dua NULL bertabrakan.
            //
            // VIRTUAL, bukan STORED seperti super_admin_lock: MySQL menolak
            // kolom turunan STORED atas kolom yang ber-FK CASCADE (#1215).
            // InnoDB tetap bisa memasang indeks unique di kolom virtual.
            $table->unsignedBigInteger('open_activity_lock')
                ->virtualAs("case when status = 'open' then activity_id end")
                ->nullable();
            $table->unique('open_activity_lock');
        });

        // Tiket lama: tunjuk activity yang memang disengketakan. Task satu
        // mitra selalu tepat satu; task banyak mitra yang ambigu dibiarkan
        // NULL dan diputuskan lewat jalur task (lihat ResolveDisputeAction).
        DB::table('task_disputes')->whereNull('activity_id')->orderBy('id')->each(function (object $dispute): void {
            $rejected = DB::table('activities')
                ->where('task_id', $dispute->task_id)
                ->where('status', 'rejected')
                ->pluck('id');
            $all = DB::table('activities')->where('task_id', $dispute->task_id)->pluck('id');
            $activityId = match (true) {
                $rejected->count() === 1 => $rejected->first(),
                $all->count() === 1 => $all->first(),
                default => null,
            };

            if ($activityId !== null) {
                DB::table('task_disputes')->where('id', $dispute->id)->update(['activity_id' => $activityId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('task_disputes', function (Blueprint $table): void {
            $table->dropUnique(['open_activity_lock']);
            $table->dropColumn('open_activity_lock');
            $table->dropConstrainedForeignId('activity_id');
            $table->dropColumn(['category', 'worker_response', 'worker_evidence_photos', 'worker_responded_at']);
        });
    }
};
