<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Profil pekerja tidak bisa dibuka sebelum identitas akunnya lengkap.
 *
 * Jenis kelamin dan tanggal lahir ada di `users`, bukan di `user_workers`, dan
 * diubah lewat `PATCH /me`. Penjaga ini di pintu tulis profil pekerja karena
 * di situlah kekosongannya berarti: kartu pekerja yang dibaca pemberi kerja
 * menampilkan keduanya, dan kartu berisi dua tanda hubung bukan kartu yang
 * bisa dipakai memilih orang.
 *
 * Ia TIDAK menerima kedua field itu sendiri, walau satu panggilan akan lebih
 * enak untuk klien: identitas hanya boleh punya satu jalur tulis. Dua jalur
 * berarti dua tempat yang harus sama-sama benar setiap kali aturannya berubah.
 */
final class ProfileIncompleteException extends DomainException
{
    /** @param list<string> $missing */
    private function __construct(private readonly array $missing, string $message)
    {
        parent::__construct($message);
    }

    /** @param list<string> $missing */
    public static function forWorkerProfile(array $missing): self
    {
        return new self($missing, sprintf(
            'Lengkapi %s lewat PATCH /me sebelum membuka profil pekerja.',
            implode(' dan ', array_map(
                fn (string $field): string => match ($field) {
                    'gender' => 'jenis kelamin',
                    'birth_date' => 'tanggal lahir',
                    default => $field,
                },
                $missing,
            )),
        ));
    }

    public function errorCode(): string
    {
        return 'profile_incomplete';
    }

    /**
     * Field yang kurang disebut satu per satu.
     *
     * Klien harus bisa menyorot isian yang tepat tanpa mengurai kalimat
     * `message` — itu teks untuk manusia, dan ia berubah.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return ['missing' => $this->missing];
    }
}
