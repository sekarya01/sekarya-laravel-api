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
}
