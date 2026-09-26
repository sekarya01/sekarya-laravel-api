<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan kemajuan per pekerja ("Tiba di lokasi dan mulai angkut lemari") — B9.
 *
 * Append-only: hanya dibuat dan dibaca. `photo` opsional (path dari uploads).
 * `(activity_id, created_at, id)` melayani `latest_update`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->string('note', 200);
            $table->string('photo', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['activity_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_updates');
    }
};
