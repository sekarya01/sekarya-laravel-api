<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('ulid', 26)->unique()->after('id');
            $table->string('phone', 20)->unique()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            // Foto tampilan, PUBLIK. Foto verifikasi ada di user_verifications.
            $table->string('avatar_path')->nullable()->after('name');
            $table->text('bio')->nullable()->after('avatar_path');
            $table->json('skills')->nullable()->after('bio');
            $table->string('active_mode', 12)->default('hiring')->after('skills');
            $table->string('status', 24)->default('active')->after('active_mode');

            // Domisili — alamat ORANGNYA, bukan lokasi pekerjaan.
            $table->string('address_line')->nullable();
            $table->string('city', 80)->nullable();
            $table->string('province', 80)->nullable();
            $table->string('postal_code', 10)->nullable();

            // Agregat sebagai worker
            $table->decimal('worker_rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('worker_rating_count')->default(0);
            $table->unsignedInteger('tasks_completed')->default(0);
            $table->unsignedInteger('bids_won')->default(0);

            // Agregat sebagai poster
            $table->decimal('poster_rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('poster_rating_count')->default(0);
            $table->unsignedInteger('tasks_posted')->default(0);

            $table->unsignedInteger('cancellations')->default(0);
            $table->string('theme', 12)->default('system');
            $table->timestamp('last_active_at')->nullable();
            $table->softDeletes();

            $table->index('created_at');
            $table->index(['status', 'active_mode']);
            $table->index(['city', 'province']);
            $table->index(['worker_rating_avg', 'tasks_completed']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['worker_rating_avg', 'tasks_completed']);
            $table->dropIndex(['city', 'province']);
            $table->dropIndex(['status', 'active_mode']);
            $table->dropIndex(['created_at']);
            $table->dropUnique(['ulid']);
            $table->dropUnique(['phone']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'ulid', 'phone', 'phone_verified_at', 'avatar_path', 'bio', 'skills',
                'active_mode', 'status', 'address_line', 'city', 'province', 'postal_code',
                'worker_rating_avg', 'worker_rating_count', 'tasks_completed', 'bids_won',
                'poster_rating_avg', 'poster_rating_count', 'tasks_posted',
                'cancellations', 'theme', 'last_active_at',
            ]);
        });
    }
};
