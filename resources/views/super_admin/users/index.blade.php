@extends('super_admin.layout')

@section('title', 'Moderasi pengguna')

@section('content')
<h2 class="text-2xl font-semibold">Moderasi pengguna</h2>
<p class="mt-1 text-sm text-slate-500"><span class="font-mono">email</span> adalah pencocokan persis (terindeks) — bukan pencarian sebagian. Tidak ada LIKE di proyek ini.</p>

<form method="GET" class="mt-4 flex flex-wrap gap-2 text-sm">
    <input name="email" value="{{ $filterEmail }}" placeholder="email persis, mis. budi@sekarya.test" class="rounded border border-slate-300 px-3 py-2 w-72">
    <select name="status" class="rounded border border-slate-300 px-3 py-2">
        <option value="">Semua status</option>
        @foreach (['active','pending_verification','suspended','banned'] as $s)
            <option value="{{ $s }}" @selected($filterStatus === $s)>{{ $s }}</option>
        @endforeach
    </select>
    <button class="rounded bg-slate-900 px-4 py-2 text-white">Cari</button>
</form>

<div class="mt-4 overflow-x-auto rounded-xl bg-white shadow">
    <table class="w-full text-sm">
        <thead>
        <tr class="text-left text-slate-500">
            <th class="px-4 py-3">Nama</th>
            <th class="px-4 py-3">Email</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Dibuat</th>
            <th class="px-4 py-3"></th>
        </tr>
        </thead>
        <tbody>
        @forelse ($queue as $u)
            <tr class="border-t">
                <td class="px-4 py-3">{{ $u->name }}</td>
                <td class="px-4 py-3">{{ $u->email }}</td>
                <td class="px-4 py-3 font-mono text-xs">{{ $u->status->value }}</td>
                <td class="px-4 py-3 whitespace-nowrap">{{ $u->created_at }}</td>
                <td class="px-4 py-3 text-right"><a href="{{ route('super_admin.users.show', $u->ulid) }}" class="underline">Buka</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-6 text-slate-500">Tidak ada hasil. Alamat yang tidak terdaftar mengembalikan daftar kosong.</td></tr>
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
