<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use App\Enums\Gender;
use App\Enums\UserActiveMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProfileRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'gender' => ['sometimes', 'nullable', Rule::enum(Gender::class)],

            // Tanggal, BUKAN umur — umur dihitung darinya (User::age()).
            // Menerima `age` dari klien berarti menerima angka yang sudah
            // salah pada hari ulang tahun pengirimnya, dan tidak ada cara
            // memperbaikinya di sisi server.
            //
            // `date_format:Y-m-d` dipasang supaya bentuknya persis satu.
            // Tanpa itu Laravel menerima apa pun yang bisa diurai strtotime —
            // termasuk "next tuesday", dan termasuk "01/02/2003" yang artinya
            // berbeda di dua benua.
            'birth_date' => [
                'sometimes', 'nullable', 'date_format:Y-m-d',
                'before_or_equal:'.$this->oldestBirthDateAllowed(),
                'after_or_equal:'.$this->youngestBirthDateAllowed(),
            ],
            'bio' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'skills' => ['sometimes', 'array', 'max:20'],
            'skills.*' => ['string', 'exists:skills,slug'],
            // Avatar itu foto PUBLIK. Foto verifikasi punya endpoint sendiri.
            'avatar_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:80'],
            'province' => ['sometimes', 'nullable', 'string', 'max:80'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'active_mode' => ['sometimes', Rule::enum(UserActiveMode::class)],
            'theme' => ['sometimes', 'in:light,dark,system'],
        ];
    }

    /** Tanggal lahir paling BARU yang masih memenuhi umur minimum. */
    private function oldestBirthDateAllowed(): string
    {
        return now()->subYears((int) config('sekarya.profile.min_age'))->toDateString();
    }

    /** Tanggal lahir paling LAMA yang masih masuk akal. */
    private function youngestBirthDateAllowed(): string
    {
        return now()->subYears((int) config('sekarya.profile.max_age'))->toDateString();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'birth_date.date_format' => 'Tanggal lahir harus berformat YYYY-MM-DD.',
            'birth_date.before_or_equal' => sprintf(
                'Umur minimum %d tahun.',
                config('sekarya.profile.min_age'),
            ),
            'birth_date.after_or_equal' => 'Tanggal lahir tidak masuk akal.',
        ];
    }
}
