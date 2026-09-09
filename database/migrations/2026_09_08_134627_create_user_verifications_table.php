<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->string('status', 24)->default('pending');

            // Foto — HANYA path, berkasnya di storage privat. Jangan pernah di public/.
            $table->string('id_card_photo_path')->nullable();
            $table->string('selfie_photo_path')->nullable();
            $table->decimal('face_match_score', 5, 2)->nullable();

            // NIK: hash untuk dicari (deteksi duplikat), enc untuk dibaca saat sengketa.
            $table->char('document_number_hash', 64)->nullable();
            $table->binary('document_number_enc')->nullable();
            $table->string('name_on_document', 120)->nullable();
            $table->date('birth_date_on_document')->nullable();

            $table->string('bank_code', 20)->nullable();
            $table->binary('account_number_enc')->nullable();
            $table->string('account_holder_name', 120)->nullable();

            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type', 'status']);
            $table->index('document_number_hash');
            $table->index(['status', 'submitted_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_verifications');
    }
};
