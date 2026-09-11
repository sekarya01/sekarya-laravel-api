<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Data\User\UpdateProfileData;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\ConnectionInterface;

final class UpdateProfileAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function handle(UpdateProfileData $data, User $user): User
    {
        return $this->db->transaction(function () use ($data, $user): User {
            $attributes = $data->toAttributes();

            if ($attributes !== []) {
                $user->fill($attributes)->save();
            }

            $this->syncDisplayName($data, $user);

            // Keahlian kini relasi berindeks, bukan kolom JSON — jadi disinkronkan,
            // tidak ditimpa sebagai nilai.
            if ($data->skills !== null) {
                $user->skills()->sync(
                    Skill::query()->whereIn('slug', $data->skills)->pluck('id'),
                );
            }

            return $user->refresh()->load('skills');
        });
    }

    /**
     * Jaga `name` tetap sinkron dengan depan+belakang.
     *
     * Warisan `name` saja (klien lama) dipecah: kata pertama jadi depan,
     * sisanya jadi belakang. Field baru saja → `name` dirakit ulang.
     */
    private function syncDisplayName(UpdateProfileData $data, User $user): void
    {
        $touched = array_intersect($data->present, ['name', 'first_name', 'last_name']);

        if ($touched === []) {
            return;
        }

        $legacyOnly = $touched === ['name'];

        if ($legacyOnly) {
            $parts = preg_split('/\s+/', trim((string) $user->name), 2);
            $user->forceFill([
                'first_name' => $parts[0] !== '' ? $parts[0] : $user->name,
                'last_name' => $parts[1] ?? null,
            ])->save();

            return;
        }

        $user->forceFill([
            'name' => trim((string) $user->first_name.' '.((string) $user->last_name)),
        ])->save();
    }
}
