@extends('super_admin.layout')

@section('title', 'Antrean verifikasi')

@section('content')
<h2 class="text-2xl font-semibold">Antrean verifikasi</h2>
<p class="mt-1 text-sm text-slate-500">Paling lama menunggu di depan. Daftar ini tidak memuat NIK, nomor rekening, maupun path foto.</p>

<form method="GET" class="mt-4 flex flex-wrap gap-2 text-sm">
    <select name="status" class="rounded border border-slate-300 px-3 py-2">
        <option value="__pending__" @selected($filterStatus === '__pending__')>Menunggu keputusan</option>
        <option value="" @selected($filterStatus === '')>Semua status (termasuk riwayat)</option>
        @foreach (['pending','in_review','verified','rejected','revoked'] as $s)
            <option value="{{ $s }}" @selected($filterStatus === $s)>{{ $s }}</option>
        @endforeach
    </select>
    <select name="type" class="rounded border border-slate-300 px-3 py-2">
        <option value="">Semua jenis</option>
        @foreach (['identity','bank_account'] as $t)
            <option value="{{ $t }}" @selected($filterType === $t)>{{ $t }}</option>
        @endforeach
    </select>
    <button class="rounded bg-slate-900 px-4 py-2 text-white">Saring</button>
</form>

<div class="mt-4 overflow-x-auto rounded-xl bg-white shadow">
    <table class="w-full text-sm">
        <thead>
        <tr class="text-left text-slate-500">
            <th class="px-4 py-3">Diajukan</th>
            <th class="px-4 py-3">Jenis</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Pengguna</th>
            <th class="px-4 py-3"></th>
        </tr>
        </thead>
        <tbody>
        @forelse ($queue as $v)
            <tr class="border-t">
                <td class="px-4 py-3 whitespace-nowrap">{{ $v->submitted_at }}</td>
                <td class="px-4 py-3 font-mono text-xs">{{ $v->type->value }}</td>
                <td class="px-4 py-3 font-mono text-xs">{{ $v->status->value }}</td>
                <td class="px-4 py-3">{{ $v->user?->email }}</td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('super_admin.verifications.show', $v) }}" class="underline">Buka</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-6 text-slate-500">Antrean kosong.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4 flex gap-2 text-sm">
    @if ($queue->previousCursor())
        <a href="{{ $queue->previousPageUrl() }}" class="rounded border px-3 py-2 bg-white">← Sebelumnya</a>
    @endif
    @if ($queue->hasMorePages())
        <a href="{{ $queue->nextPageUrl() }}" class="rounded border px-3 py-2 bg-white">Berikutnya →</a>
    @endif
</div>
@endsection
