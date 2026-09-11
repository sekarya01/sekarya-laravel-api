<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Enums\Gender;
use App\Enums\UserActiveMode;
use App\Http\Requests\Api\V1\User\UpdateProfileRequest;
use Carbon\CarbonImmutable;

final readonly class UpdateProfileData
{
    /**
     * @param  list<string>|null  $skills
     * @param  list<string>  $present  Kunci yang BENAR-BENAR ada di payload.
     *
     * `$present` memisahkan "tidak dikirim" dari "dikirim bernilai null", dan
     * itu bukan kehalusan: aturan validasi menandai `bio`, `address_line`,
     * `gender` dan seluruh kolom opsional lainnya `nullable` — artinya API
     * MENJANJIKAN null bisa dikirim. Tanpa daftar ini, satu-satunya cara
     * membedakannya adalah nilai propertinya sendiri, yang null pada kedua
     * keadaan, sehingga `{"bio": null}` dijawab 200 dan tidak mengubah apa
     * pun. Permintaan yang diterima tapi diam-diam tidak dikerjakan lebih
     * buruk daripada permintaan yang ditolak.
     *
     * Kosong = perilaku lama: hanya nilai non-null yang ikut. Itu yang dipakai
     * saat DTO dibangun langsung (test unit), di mana tidak ada payload untuk
     * ditanyai.
     */
    public function __construct(
        public ?string $name = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $username = null,
        public ?Gender $gender = null,
        public ?CarbonImmutable $birthDate = null,
        public ?string $bio = null,
        public ?array $skills = null,
        public ?string $avatarPath = null,
        public ?string $addressLine = null,
        public ?string $city = null,
        public ?string $province = null,
        public ?string $postalCode = null,
        public ?UserActiveMode $activeMode = null,
        public ?string $theme = null,
        public array $present = [],
    ) {}

    public static function fromRequest(UpdateProfileRequest $request): self
    {
        $str = fn (string $key): ?string => $request->has($key)
            ? ($request->filled($key) ? trim($request->string($key)->value()) : null)
            : null;

        return new self(
            name: $str('name'),
            firstName: $str('first_name'),
            lastName: $str('last_name'),
            username: $request->has('username')
                ? ($request->filled('username') ? mb_strtolower(trim($request->string('username')->value())) : null)
                : null,
            gender: $request->filled('gender')
                ? Gender::from($request->string('gender')->value())
                : null,
            // `->date()` menguraikan dengan format yang sama seperti aturan
            // validasinya, jadi tidak ada dua penafsiran untuk satu string.
            birthDate: $request->filled('birth_date')
                ? $request->date('birth_date', 'Y-m-d')?->toImmutable()->startOfDay()
                : null,
            bio: $str('bio'),
            skills: $request->has('skills')
                ? array_values(array_filter(array_map('trim', $request->array('skills'))))
                : null,
            avatarPath: $str('avatar_path'),
            addressLine: $str('address_line'),
            city: $str('city'),
            province: $str('province'),
            postalCode: $str('postal_code'),
            activeMode: $request->filled('active_mode')
                ? UserActiveMode::from($request->string('active_mode')->value())
                : null,
            theme: $str('theme'),
            // `skills` sengaja tidak masuk: ia relasi, bukan kolom, dan
            // disinkronkan terpisah di Action-nya.
            present: array_values(array_intersect(
                array_keys(self::COLUMN_MAP),
                array_keys($request->all()),
            )),
        );
    }

    /**
     * Nama field di payload -> nama kolom. Satu tempat, dipakai dua arah:
     * menyusun atribut, dan menentukan field mana yang boleh dianggap "ada".
     */
    private const array COLUMN_MAP = [
        'name' => 'name',
        'first_name' => 'first_name',
        'last_name' => 'last_name',
        'username' => 'username',
        'gender' => 'gender',
        'birth_date' => 'birth_date',
        'bio' => 'bio',
        'avatar_path' => 'avatar_path',
        'address_line' => 'address_line',
        'city' => 'city',
        'province' => 'province',
        'postal_code' => 'postal_code',
        'active_mode' => 'active_mode',
        'theme' => 'theme',
    ];

    /**
     * Hanya field yang benar-benar dikirim — nilainya boleh null, dan null di
     * sini BERARTI kosongkan kolomnya.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $values = [
            'name' => $this->name,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'username' => $this->username,
            'gender' => $this->gender,
            'birth_date' => $this->birthDate,
            'bio' => $this->bio,
            'avatar_path' => $this->avatarPath,
            'address_line' => $this->addressLine,
            'city' => $this->city,
            'province' => $this->province,
            'active_mode' => $this->activeMode,
            'postal_code' => $this->postalCode,
            'theme' => $this->theme,
        ];

        if ($this->present === []) {
            return array_filter($values, fn (mixed $v): bool => $v !== null);
        }

        $columns = array_map(
            fn (string $field): string => self::COLUMN_MAP[$field],
            $this->present,
        );

        return array_intersect_key($values, array_flip($columns));
    }
}
