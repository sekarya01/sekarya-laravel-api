@extends('super_admin.layout')

@section('title', 'Antrean verifikasi')
@section('header', 'Verifikasi Identitas')

@section('content')
<div class="anim-rise">
    <h3 class="text-xl font-extrabold" style="color: #163C68;">Antrean verifikasi</h3>
    <p class="mt-1 text-sm text-slate-500">Paling lama menunggu di depan. Daftar ini <strong>tidak memuat</strong> NIK, nomor rekening, maupun path foto.</p>
</div>

<form method="GET" class="anim-rise ad-1 mt-4 card p-4 flex flex-wrap items-end gap-3 text-sm">
    <div>
        <label class="label">Status</label>
        <select name="status" class="field w-auto! min-w-[13rem]">
            <option value="__pending__" @selected($filterStatus === '__pending__')>Menunggu keputusan</option>
            <option value="" @selected($filterStatus === '')>Semua status (termasuk riwayat)</option>
            @foreach (['pending' => 'amber', 'in_review' => 'navy', 'verified' => 'green', 'rejected' => 'red', 'revoked' => 'slate'] as $s => $t)
                <option value="{{ $s }}" @selected($filterStatus === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label">Jenis</label>
        <select name="type" class="field w-auto! min-w-[11rem]">
            <option value="">Semua jenis</option>
            @foreach (['identity' => 'KTP / identitas', 'bank_account' => 'Rekening'] as $t => $label)
                <option value="{{ $t }}" @selected($filterType === $t)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn btn-accent">Saring</button>
</form>

<div class="anim-rise ad-2 mt-4 card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[680px]">
            <thead><tr class="table-head">
                <th>Diajukan</th><th class="th-c">Jenis</th><th class="th-c">Status</th><th class="th-c">Pengguna</th><th class="th-c">Aksi</th>
            </tr></thead>
            <tbody>
            @forelse ($queue as $v)
                @php
                    $tone = match($v->status->value) {
                        'verified' => 'green', 'rejected' => 'red', 'revoked' => 'slate',
                        'in_review' => 'navy', default => 'amber',
                    };
                @endphp
                <tr class="table-row">
                    <td class="whitespace-nowrap text-slate-500 text-xs">{{ $v->submitted_at }}</td>
                    <td class="td-c">
                        @include('super_admin.partials.badge', ['text' => $v->type->value === 'identity' ? 'KTP' : 'Rekening', 'tone' => $v->type->value === 'identity' ? 'navy' : 'purple'])
                    </td>
                    <td class="td-c">@include('super_admin.partials.badge', ['text' => $v->status->value, 'tone' => $tone])</td>
                    <td class="td-c">
                        <p class="font-medium">{{ $v->user?->name }}</p>
                        <p class="text-xs text-slate-400"><span class="marq" title="{{ $v->user?->email }}"><span class="marq-in">{{ $v->user?->email }}</span></span></p>
                    </td>
                    <td class="td-c">
                        <a href="{{ route('super_admin.verifications.show', $v) }}" class="btn btn-navy py-2! px-4! text-xs!">Buka →</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">@include('super_admin.partials.empty', ['title' => 'Antrean kosong', 'hint' => 'Semua pengajuan sudah dinilai. Kerja bagus.'])</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('super_admin.partials.pager', ['paginator' => $queue])
@endsection
