<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Concerns;

use App\Data\CursorPageData;

/**
 * Dipakai FormRequest daftar apa pun. Menggabungkan aturan halaman bersama
 * dengan filter khusus endpoint, tanpa mengulang aturan itu di tiap kelas.
 */
trait PaginatesWithCursor
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [...CursorPageData::rules(), ...$this->filters()];
    }

    /**
     * Filter khusus endpoint. Kosongkan kalau tidak ada.
     *
     * @return array<string, mixed>
     */
    abstract protected function filters(): array;

    public function page(): CursorPageData
    {
        return CursorPageData::fromRequest($this);
    }
}
